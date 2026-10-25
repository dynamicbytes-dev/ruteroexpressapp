<?php

namespace App\Domain\Trips;

use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Domain\Wallet\SaldoInsuficienteException;
use App\Domain\Wallet\WalletService;
use App\Enums\PaymentMethod;
use App\Enums\TripStatus;
use App\Enums\VehicleType;
use App\Enums\WalletTransactionType;
use App\Events\ViajeActualizado;
use App\Models\City;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\User;
use App\Support\Configuracion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orquesta el ciclo de vida del viaje: cotizar, pedir, cancelar, avanzar de
 * estado y liquidar el pago. La asignación en cascada vive en `TripDispatcher`.
 */
class TripService
{
    public function __construct(
        private readonly RouteEstimator $rutas,
        private readonly FareCalculator $tarifas,
        private readonly WalletService $billetera,
        private readonly TripDispatcher $asignador,
        private readonly CouponService $cupones,
    ) {}

    /**
     * Cotiza sin crear el viaje.
     *
     * @param  array{origin_lat: float|string, origin_lng: float|string, destination_lat: float|string, destination_lng: float|string, coupon_code?: string}  $datos
     * @return array<string, mixed>
     */
    public function cotizar(array $datos, ?int $cityId = null, ?User $usuario = null): array
    {
        $tipo = $this->tipoVehiculo($datos);
        $cityId ??= City::defaultId();

        $ruta = $this->rutas->estimar(
            (float) $datos['origin_lat'],
            (float) $datos['origin_lng'],
            (float) $datos['destination_lat'],
            (float) $datos['destination_lng'],
            $tipo,
            $this->velocidadPromedio($tipo),
        );

        $tarifa = $this->tarifas->calcular(
            $ruta['distance_km'],
            $ruta['duration_minutes'],
            $tipo,
            cityId: $cityId,
        );

        $tasa = ExchangeRate::vigente();

        $resultado = array_merge($tarifa, [
            'route_source' => $ruta['source'],
            'exchange_rate' => $tasa === null ? null : (float) $tasa->rate,
            'local_total' => $tasa === null ? null : round($tarifa['total'] * (float) $tasa->rate, 2),
        ]);

        // Cupón: descuento porcentual sobre el total (si lo envía el pasajero).
        $couponCode = $datos['coupon_code'] ?? null;
        if ($couponCode !== null && $usuario !== null && $usuario->isPassenger()) {
            $validacion = $this->cupones->validar($couponCode, $usuario, (float) $tarifa['total']);

            if ($validacion['valid']) {
                $resultado['coupon'] = [
                    'code' => $validacion['coupon']->code,
                    'discount_percent' => $validacion['discount_percent'],
                    'discount_amount' => $validacion['discount_amount'],
                    'payable_amount' => $validacion['payable_amount'],
                ];
                $resultado['total_with_discount'] = $validacion['payable_amount'];
            } else {
                $resultado['coupon_error'] = $validacion['message'];
            }
        }

        return $resultado;
    }

    /**
     * Crea el viaje, reserva el dinero si es billetera y lanza la cascada.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(User $pasajero, array $datos): Trip
    {
        if (! $pasajero->canOperate()) {
            throw new OperacionNoPermitidaException('Tu cuenta todavía no está habilitada para pedir viajes.');
        }

        $tipo = $this->tipoVehiculo($datos);
        $ciudadId = $pasajero->city_id ?? City::defaultId();

        if ($ciudadId === null) {
            throw new OperacionNoPermitidaException('Tu cuenta todavía no tiene una ciudad asignada. Escríbenos para completar tu registro.');
        }

        $ruta = $this->rutas->estimar(
            (float) $datos['origin_lat'],
            (float) $datos['origin_lng'],
            (float) $datos['destination_lat'],
            (float) $datos['destination_lng'],
            $tipo,
            $this->velocidadPromedio($tipo),
        );

        $tarifa = $this->tarifas->calcular(
            $ruta['distance_km'],
            $ruta['duration_minutes'],
            $tipo,
            cityId: $ciudadId,
        );
        $metodo = PaymentMethod::from((string) $datos['payment_method']);
        $tasa = ExchangeRate::vigente();

        // ------------------------------------------------------------------
        // Descuentos: primeros 2 viajes gratis y cupón del auditor.
        // El conductor siempre recibe su driver_earning completo. La
        // diferencia la asume el sistema como subsidio.
        // ------------------------------------------------------------------
        $discountPercent = 0.0;
        $discountAmount = 0.0;
        $subsidyAmount = 0.0;
        $promoType = null;
        $coupon = null;

        // 1. Primeros 2 viajes gratis (solo pasajeros; se cuentan los viajes pedidos).
        if ($pasajero->isPassenger()) {
            $viajesPrevios = Trip::where('passenger_id', $pasajero->getKey())->count();

            if ($viajesPrevios < 2) {
                $promoType = 'first_trips';
                $discountPercent = 100.0;
                $discountAmount = (float) $tarifa['total'];
                $subsidyAmount = (float) $tarifa['total'];
            }
        }

        // 2. Cupón del auditor (solo si no aplica la promo de primeros viajes).
        $couponCode = $datos['coupon_code'] ?? null;
        if ($promoType === null && $couponCode !== null) {
            $validacion = $this->cupones->validar($couponCode, $pasajero, (float) $tarifa['total']);

            if ($validacion['valid']) {
                $promoType = 'coupon';
                $coupon = $validacion['coupon'];
                $discountPercent = $validacion['discount_percent'];
                $discountAmount = $validacion['discount_amount'];
                $subsidyAmount = $validacion['discount_amount'];
            }
        }

        $passengerPays = round((float) $tarifa['total'] - $discountAmount, 2);

        $viaje = DB::transaction(function () use ($pasajero, $datos, $ruta, $tarifa, $metodo, $tipo, $tasa, $ciudadId, $discountPercent, $discountAmount, $subsidyAmount, $promoType, $coupon, $passengerPays): Trip {
            $billetera = $this->billetera->paraUsuario($pasajero);

            if ($metodo === PaymentMethod::Wallet) {
                $disponible = $this->billetera->disponible($billetera);

                // El pasajero solo necesita saldo para lo que realmente pagará.
                if ($disponible < $passengerPays) {
                    throw SaldoInsuficienteException::paraOperar($disponible, $passengerPays);
                }
            }

            $viaje = Trip::create([
                'passenger_id' => $pasajero->getKey(),
                'city_id' => $ciudadId,
                'coupon_id' => $coupon?->getKey(),
                'status' => TripStatus::Searching,
                'payment_method' => $metodo,
                'vehicle_type' => $tipo,
                'origin_address' => $datos['origin_address'],
                'destination_address' => $datos['destination_address'],
                'origin_lat' => $datos['origin_lat'],
                'origin_lng' => $datos['origin_lng'],
                'destination_lat' => $datos['destination_lat'],
                'destination_lng' => $datos['destination_lng'],
                'distance_km' => $tarifa['distance_km'],
                'duration_minutes' => $tarifa['duration_minutes'],
                'base_fare' => $tarifa['base_fare'],
                'price_per_km' => $tarifa['price_per_km'],
                'price_per_minute' => $tarifa['price_per_minute'],
                'minimum_fare' => $tarifa['minimum_fare'],
                'minimum_applied' => $tarifa['minimum_applied'],
                'dynamic_multiplier' => $tarifa['dynamic_multiplier'],
                'night_surcharge_percent' => $tarifa['night_surcharge_percent'],
                'subtotal' => $tarifa['subtotal'],
                'total' => $tarifa['total'],
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'subsidy_amount' => $subsidyAmount,
                'promo_type' => $promoType,
                'commission_percent' => $tarifa['commission_percent'],
                'commission_amount' => $tarifa['commission_amount'],
                'driver_earning' => $tarifa['driver_earning'],
                'exchange_rate' => $tasa?->rate,
                'fare_breakdown' => array_merge($tarifa, [
                    'route_source' => $ruta['source'],
                ]),
                'wallet_hold_amount' => 0,
                'search_radius_index' => 0,
                'requested_at' => now(),
            ]);

            if ($metodo === PaymentMethod::Wallet) {
                // Si el viaje es gratis (primeros viajes o cupón 100%), no hay retención.
                if ($passengerPays > 0) {
                    $this->billetera->retener($billetera, $passengerPays);
                    $viaje->forceFill(['wallet_hold_amount' => $passengerPays])->save();
                }
            }

            $this->asignador->iniciarBusqueda($viaje);

            return $viaje->refresh();
        });

        // Registrar uso del cupón después de crear el viaje (fuera de la
        // transacción principal para no acoplar la creación del viaje al cupón).
        if ($coupon !== null && $promoType === 'coupon') {
            $this->cupones->registrarUso($coupon, $pasajero, $viaje);
        }

        return $viaje->load(['passenger', 'driver.driverProfile', 'currentOffer', 'rating']);
    }

    /**
     * Cancela el viaje. Si cancela el conductor antes de empezar, el viaje
     * vuelve a búsqueda y cuenta como rechazo; si cancela el pasajero, termina.
     */
    public function cancelar(Trip $viaje, User $usuario, ?string $motivo = null): Trip
    {
        return DB::transaction(function () use ($viaje, $usuario, $motivo): Trip {
            /** @var Trip $viaje */
            $viaje = Trip::query()->whereKey($viaje->getKey())->lockForUpdate()->firstOrFail();

            if (! $viaje->belongsToUser($usuario)) {
                throw new RuntimeException('Este viaje no es tuyo.');
            }

            if (! $viaje->canBeCancelled()) {
                throw new RuntimeException('Este viaje ya no se puede cancelar.');
            }

            $esConductor = $viaje->driver_id === $usuario->getKey();
            $conductorAnterior = $viaje->driver_id;
            $motivo = $motivo === null ? null : trim($motivo);

            // Un conductor no puede abandonar un viaje que ya empezó.
            if ($esConductor && $viaje->status === TripStatus::InProgress) {
                throw new RuntimeException('El viaje ya empezó: no se puede cancelar desde la app.');
            }

            $this->asignador->cancelarOfertaPendiente($viaje);

            if ($esConductor && $viaje->status->isAssigned()) {
                $this->asignador->penalizarConductor($usuario);

                $viaje->update([
                    'status' => TripStatus::Searching,
                    'driver_id' => null,
                    'accepted_at' => null,
                    'arrived_at' => null,
                    'started_at' => null,
                ]);

                $this->asignador->iniciarBusqueda($viaje);

                $viaje = $viaje->refresh()->load(['passenger', 'driver.driverProfile', 'currentOffer', 'rating']);

                ViajeActualizado::dispatch(
                    $viaje,
                    [$viaje->passenger_id, $conductorAnterior],
                    'conductor_cancelo',
                );

                return $viaje;
            }

            $this->liberarHold($viaje);

            $viaje->update([
                'status' => TripStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $esConductor ? 'driver' : 'passenger',
                'cancellation_reason' => $motivo,
            ]);

            $viaje = $viaje->refresh();

            ViajeActualizado::dispatch(
                $viaje,
                [$viaje->passenger_id, $conductorAnterior],
                'cancelado',
            );

            return $viaje;
        });
    }

    public function marcarLlegada(Trip $viaje, User $conductor): Trip
    {
        return $this->transicion($viaje, $conductor, TripStatus::Accepted, TripStatus::Arriving, [
            'arrived_at' => null,
        ]);
    }

    public function marcarEsperando(Trip $viaje, User $conductor): Trip
    {
        return $this->transicion($viaje, $conductor, TripStatus::Arriving, TripStatus::Waiting, [
            'arrived_at' => now(),
        ]);
    }

    public function iniciar(Trip $viaje, User $conductor): Trip
    {
        return $this->transicion($viaje, $conductor, TripStatus::Waiting, TripStatus::InProgress, [
            'started_at' => now(),
        ]);
    }

    public function finalizar(Trip $viaje, User $conductor): Trip
    {
        return DB::transaction(function () use ($viaje, $conductor): Trip {
            /** @var Trip $viaje */
            $viaje = Trip::query()->whereKey($viaje->getKey())->lockForUpdate()->firstOrFail();

            if ($viaje->driver_id !== $conductor->getKey()) {
                throw new RuntimeException('Este viaje no está asignado a ti.');
            }

            if ($viaje->status !== TripStatus::InProgress) {
                throw new RuntimeException('El viaje todavía no está en curso.');
            }

            $this->liquidar($viaje, $conductor);

            $viaje->update([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
                'wallet_hold_amount' => 0,
            ]);

            DriverProfile::query()
                ->where('user_id', $conductor->getKey())
                ->increment('trips_completed');

            $viaje = $viaje->refresh();

            ViajeActualizado::dispatch(
                $viaje,
                [$viaje->passenger_id, $viaje->driver_id],
                'finalizado',
            );

            return $viaje;
        });
    }

    /** @return array<int, string> */
    public function estadosActivos(): array
    {
        return array_map(fn (TripStatus $estado): string => $estado->value, TripStatus::active());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transicion(
        Trip $viaje,
        User $conductor,
        TripStatus $desde,
        TripStatus $hacia,
        array $extra = [],
    ): Trip {
        return DB::transaction(function () use ($viaje, $conductor, $desde, $hacia, $extra): Trip {
            /** @var Trip $viaje */
            $viaje = Trip::query()->whereKey($viaje->getKey())->lockForUpdate()->firstOrFail();

            if ($viaje->driver_id !== $conductor->getKey()) {
                throw new RuntimeException('Este viaje no está asignado a ti.');
            }

            if ($viaje->status !== $desde) {
                throw new RuntimeException("El viaje no está en el estado necesario para pasar a {$hacia->label()}.");
            }

            $viaje->update(array_merge(['status' => $hacia], $extra));

            $viaje = $viaje->refresh();

            ViajeActualizado::dispatch(
                $viaje,
                [$viaje->passenger_id, $viaje->driver_id],
                'estado',
            );

            return $viaje;
        });
    }

    private function tipoVehiculo(array $datos): VehicleType
    {
        return VehicleType::tryFrom((string) ($datos['vehicle_type'] ?? VehicleType::Motorcycle->value))
            ?? VehicleType::Motorcycle;
    }

    private function velocidadPromedio(VehicleType $tipo): float
    {
        $respaldo = $tipo === VehicleType::Car ? 30.0 : 25.0;

        return Configuracion::decimal("velocidad_promedio_{$tipo->value}_kmh", $respaldo);
    }

    private function liquidar(Trip $viaje, User $conductor): void
    {
        $passengerPays = round((float) $viaje->total - (float) $viaje->discount_amount, 2);

        if ($viaje->payment_method === PaymentMethod::Wallet) {
            $pasajero = $viaje->passenger ?? User::query()->findOrFail($viaje->passenger_id);
            $billeteraPasajero = $this->billetera->paraUsuario($pasajero);
            $billeteraConductor = $this->billetera->paraUsuario($conductor);

            $this->billetera->transferirPagoViaje(
                $billeteraPasajero,
                $billeteraConductor,
                $passengerPays,
                (float) $viaje->driver_earning,
                (float) $viaje->subsidy_amount,
                $viaje,
            );

            return;
        }

        // Efectivo: el pasajero paga fuera de la app y el conductor debe la
        // comisión. Puede quedar negativo, pero si supera el tope se desconecta.
        $billetera = $this->billetera->paraUsuario($conductor);

        if ((float) $viaje->commission_amount <= 0) {
            return;
        }

        DB::transaction(function () use ($viaje, $conductor, $billetera): void {
            $this->billetera->debitar(
                $billetera,
                (float) $viaje->commission_amount,
                WalletTransactionType::Commission,
                "Comisión de viaje en efectivo · {$viaje->origin_address} → {$viaje->destination_address}",
                $viaje,
                piso: -999999999.99,
            );

            // Subsidio para viajes en efectivo con descuento: el sistema cubre
            // lo que el pasajero no pagó en efectivo.
            if ((float) $viaje->subsidy_amount > 0) {
                $this->billetera->acreditar(
                    $this->billetera->paraUsuario($conductor),
                    (float) $viaje->subsidy_amount,
                    WalletTransactionType::Subsidy,
                    "Subsidio por descuento aplicado · {$viaje->origin_address} → {$viaje->destination_address}",
                    $viaje,
                );
            }

            $tope = Configuracion::decimal('tope_saldo_negativo', -5.0);

            if ((float) $billetera->fresh()->balance < $tope) {
                DriverProfile::query()
                    ->where('user_id', $conductor->getKey())
                    ->update([
                        'is_online' => false,
                        'blocked_reason' => 'Saldo negativo por encima del tope. Recarga para volver a activarte.',
                    ]);
            }
        });
    }

    private function liberarHold(Trip $viaje): void
    {
        $monto = (float) $viaje->wallet_hold_amount;

        if ($monto <= 0) {
            return;
        }

        $this->billetera->liberarRetencion(
            $this->billetera->paraUsuario($viaje->passenger),
            $monto,
        );

        $viaje->forceFill(['wallet_hold_amount' => 0])->save();
    }
}
