<?php

namespace App\Domain\Trips;

use App\Domain\Notifications\NotificarAuditores;
use App\Domain\Wallet\WalletService;
use App\Enums\TripOfferStatus;
use App\Enums\TripStatus;
use App\Events\OfertaViajeCreada;
use App\Events\ViajeActualizado;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Support\Configuracion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Asignación en cascada: busca al conductor más cercano, le envía una alerta
 * y, si rechaza o deja vencer el tiempo, pasa al siguiente y amplía el radio.
 *
 * Los rechazos y las alertas vencidas cuentan para la penalización. El bloqueo
 * se guarda en `driver_profiles.blocked_until`, separado del bloqueo de cuenta.
 */
class TripDispatcher
{
    public function __construct(
        private readonly WalletService $billetera,
        private readonly NotificarAuditores $notificar,
    ) {}

    /** Empieza (o reinicia) la búsqueda de conductor para un viaje. */
    public function iniciarBusqueda(Trip $trip): ?TripOffer
    {
        return DB::transaction(function () use ($trip): ?TripOffer {
            /** @var Trip $trip */
            $trip = Trip::query()->whereKey($trip->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($trip->status, [TripStatus::Searching, TripStatus::Offered], true)) {
                return $trip->currentOffer;
            }

            return $this->ofrecerSiguiente($trip);
        });
    }

    public function aceptar(TripOffer $oferta, User $conductor): Trip
    {
        return DB::transaction(function () use ($oferta, $conductor): Trip {
            /** @var TripOffer $oferta */
            $oferta = TripOffer::query()->whereKey($oferta->getKey())->lockForUpdate()->firstOrFail();

            if (! $oferta->isPending()) {
                throw new RuntimeException('Esta alerta ya no está disponible.');
            }

            if ($oferta->driver_id !== $conductor->getKey()) {
                throw new RuntimeException('Esta alerta no es tuya.');
            }

            if ($oferta->isExpired()) {
                $this->expirarOfertaBloqueada($oferta);

                throw new RuntimeException('El tiempo para aceptar venció. La alerta pasó al siguiente conductor.');
            }

            /** @var Trip $trip */
            $trip = Trip::query()->whereKey($oferta->trip_id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== TripStatus::Offered || $trip->driver_id !== $conductor->getKey()) {
                throw new RuntimeException('El viaje ya no está disponible.');
            }

            $perfil = DriverProfile::query()
                ->where('user_id', $conductor->getKey())
                ->lockForUpdate()
                ->first();

            if ($perfil === null || ! $perfil->isAvailable()) {
                throw new RuntimeException('No puedes aceptar viajes en este momento. Revisa tu conexión o tu saldo.');
            }

            $oferta->update([
                'status' => TripOfferStatus::Accepted,
                'responded_at' => now(),
            ]);

            TripOffer::query()
                ->where('trip_id', $trip->getKey())
                ->whereKeyNot($oferta->getKey())
                ->where('status', TripOfferStatus::Pending)
                ->update([
                    'status' => TripOfferStatus::Cancelled,
                    'responded_at' => now(),
                ]);

            $trip->update([
                'status' => TripStatus::Accepted,
                'driver_id' => $conductor->getKey(),
                'accepted_at' => now(),
            ]);

            // Aceptar limpia el contador de rechazos de la ventana.
            $perfil->forceFill([
                'rejections_in_window' => 0,
                'rejection_window_started_at' => null,
            ])->save();

            $trip = $trip->refresh();

            ViajeActualizado::dispatch(
                $trip,
                [$trip->passenger_id, $conductor->getKey()],
                'aceptado',
            );

            return $trip;
        });
    }

    public function rechazar(TripOffer $oferta, User $conductor): Trip
    {
        return DB::transaction(function () use ($oferta, $conductor): Trip {
            /** @var TripOffer $oferta */
            $oferta = TripOffer::query()->whereKey($oferta->getKey())->lockForUpdate()->firstOrFail();

            if (! $oferta->isPending()) {
                throw new RuntimeException('Esta alerta ya no está disponible.');
            }

            if ($oferta->driver_id !== $conductor->getKey()) {
                throw new RuntimeException('Esta alerta no es tuya.');
            }

            $oferta->update([
                'status' => TripOfferStatus::Rejected,
                'responded_at' => now(),
            ]);

            $this->penalizar($conductor);

            /** @var Trip $trip */
            $trip = Trip::query()->whereKey($oferta->trip_id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== TripStatus::Offered || $trip->driver_id !== $conductor->getKey()) {
                return $trip->refresh();
            }

            $trip->update([
                'status' => TripStatus::Searching,
                'driver_id' => null,
            ]);

            $this->ofrecerSiguiente($trip);

            $trip = $trip->refresh();

            ViajeActualizado::dispatch($trip, [$trip->passenger_id], 'rechazado');

            return $trip;
        });
    }

    /** Marca una alerta vencida y continúa la cascada. */
    public function expirarOferta(TripOffer $oferta): void
    {
        DB::transaction(function () use ($oferta): void {
            /** @var TripOffer $oferta */
            $oferta = TripOffer::query()->whereKey($oferta->getKey())->lockForUpdate()->firstOrFail();

            if (! $oferta->isPending()) {
                return;
            }

            $this->expirarOfertaBloqueada($oferta);
        });
    }

    /** Procesa todas las alertas vencidas (lo llama el comando programado). */
    public function expirarVencidas(): int
    {
        $ofertas = TripOffer::query()
            ->where('status', TripOfferStatus::Pending)
            ->where('expires_at', '<=', now())
            ->get();

        $procesadas = 0;

        foreach ($ofertas as $oferta) {
            $this->expirarOferta($oferta);
            $procesadas++;
        }

        return $procesadas;
    }

    /** Un conductor que cancela un viaje aceptado recibe la misma penalización. */
    public function penalizarConductor(User $conductor): void
    {
        DB::transaction(function () use ($conductor): void {
            $this->penalizar($conductor);
        });
    }

    /**
     * El conductor se desconectó: sus alertas se cancelan y los viajes siguen
     * con el siguiente candidato. No cuenta como rechazo.
     */
    public function cancelarOfertaPendienteDeConductor(User $conductor): void
    {
        DB::transaction(function () use ($conductor): void {
            $ofertas = TripOffer::query()
                ->where('driver_id', $conductor->getKey())
                ->where('status', TripOfferStatus::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($ofertas as $oferta) {
                $oferta->update([
                    'status' => TripOfferStatus::Cancelled,
                    'responded_at' => now(),
                ]);

                /** @var Trip $trip */
                $trip = Trip::query()->whereKey($oferta->trip_id)->lockForUpdate()->first();

                if ($trip === null
                    || $trip->status !== TripStatus::Offered
                    || $trip->driver_id !== $conductor->getKey()) {
                    continue;
                }

                $trip->update([
                    'status' => TripStatus::Searching,
                    'driver_id' => null,
                ]);

                $this->ofrecerSiguiente($trip);

                $trip = $trip->refresh();

                ViajeActualizado::dispatch(
                    $trip,
                    [$trip->passenger_id, $conductor->getKey()],
                    'conductor_desconectado',
                );
            }
        });
    }

    /** Cancela la alerta pendiente cuando el pasajero cancela el viaje. */
    public function cancelarOfertaPendiente(Trip $trip): void
    {
        TripOffer::query()
            ->where('trip_id', $trip->getKey())
            ->where('status', TripOfferStatus::Pending)
            ->update([
                'status' => TripOfferStatus::Cancelled,
                'responded_at' => now(),
            ]);
    }

    /**
     * Debe ejecutarse con la oferta bloqueada y dentro de una transacción.
     */
    private function expirarOfertaBloqueada(TripOffer $oferta): void
    {
        $oferta->update([
            'status' => TripOfferStatus::Expired,
            'responded_at' => now(),
        ]);

        /** @var Trip $trip */
        $trip = Trip::query()->whereKey($oferta->trip_id)->lockForUpdate()->firstOrFail();

        if ($trip->status !== TripStatus::Offered || $trip->driver_id !== $oferta->driver_id) {
            return;
        }

        $this->penalizar($oferta->driver);

        $conductorAfectado = $oferta->driver_id;

        $trip->update([
            'status' => TripStatus::Searching,
            'driver_id' => null,
        ]);

        $this->ofrecerSiguiente($trip);

        $trip = $trip->refresh();

        ViajeActualizado::dispatch(
            $trip,
            [$trip->passenger_id, $conductorAfectado],
            'oferta_expirada',
        );
    }

    /**
     * Busca y ofrece al conductor más cercano en el radio actual. Si no hay
     * nadie, avanza al siguiente radio; si se agotan, marca `no_driver`.
     *
     * Debe ejecutarse con el viaje bloqueado y dentro de una transacción.
     */
    private function ofrecerSiguiente(Trip $trip): ?TripOffer
    {
        $radios = $this->radios();

        // Prioridad del pasajero: antes de la cascada normal se intenta al
        // conductor favorito que esté en línea dentro del radio configurado.
        // Solo en el primer intento del viaje, para no retrasar la búsqueda.
        if ($trip->offers()->doesntExist()) {
            $radioFavorito = Configuracion::integer('radio_favorito_km', 8);

            if ($radioFavorito > 0) {
                $favorito = $this->buscarConductor(
                    $trip,
                    (float) $trip->origin_lat,
                    (float) $trip->origin_lng,
                    (float) $radioFavorito,
                    soloFavoritos: true,
                );

                if ($favorito !== null) {
                    return $this->ofrecerA($trip, $favorito, 0);
                }
            }
        }

        if ($radios === []) {
            $trip->update(['status' => TripStatus::NoDriver, 'driver_id' => null]);
            $this->liberarHold($trip);

            $trip = $trip->refresh();

            ViajeActualizado::dispatch($trip, [$trip->passenger_id], 'sin_conductor');
            $this->notificarSinConductor($trip);

            return null;
        }

        $indice = min(max(0, (int) $trip->search_radius_index), count($radios) - 1);

        for (; $indice < count($radios); $indice++) {
            $candidato = $this->buscarConductor(
                $trip,
                (float) $trip->origin_lat,
                (float) $trip->origin_lng,
                (float) $radios[$indice],
            );

            if ($candidato === null) {
                continue;
            }

            return $this->ofrecerA($trip, $candidato, $indice);
        }

        $trip->update([
            'status' => TripStatus::NoDriver,
            'driver_id' => null,
        ]);
        $this->liberarHold($trip);

        $trip = $trip->refresh();

        ViajeActualizado::dispatch($trip, [$trip->passenger_id], 'sin_conductor');
        $this->notificarSinConductor($trip);

        return null;
    }

    /** Crea la alerta, asigna el candidato y avisa por WebSocket. */
    private function ofrecerA(Trip $trip, DriverProfile $candidato, int $indice): TripOffer
    {
        $segundos = max(20, Configuracion::integer('tiempo_respuesta_segundos', 20));

        $oferta = TripOffer::create([
            'trip_id' => $trip->getKey(),
            'driver_id' => $candidato->user_id,
            'status' => TripOfferStatus::Pending,
            'distance_to_pickup_km' => round(((float) $candidato->getAttribute('distance_m')) / 1000, 2),
            'offered_at' => now(),
            'expires_at' => now()->addSeconds($segundos),
        ]);

        $trip->update([
            'status' => TripStatus::Offered,
            'driver_id' => $candidato->user_id,
            'search_radius_index' => $indice,
        ]);

        $trip = $trip->refresh();

        OfertaViajeCreada::dispatch($oferta);
        ViajeActualizado::dispatch($trip, [$trip->passenger_id], 'oferta_enviada');

        return $oferta;
    }

    private function notificarSinConductor(Trip $trip): void
    {
        $this->notificar->ejecutar(
            'viaje_sin_conductor',
            'Viaje sin conductor',
            "El viaje #{$trip->id} ({$trip->origin_address} → {$trip->destination_address}) no encontró conductor disponible.",
            route('panel.viajes.index'),
            [
                'trip_id' => $trip->id,
                'vehicle_type' => $trip->vehicle_type->value,
                'total' => (float) $trip->total,
            ],
        );
    }

    private function buscarConductor(
        Trip $trip,
        float $lat,
        float $lng,
        float $radioKm,
        bool $soloFavoritos = false,
    ): ?DriverProfile {
        $tope = Configuracion::decimal('tope_saldo_negativo', -5.0);
        $estadosOcupado = [
            TripStatus::Offered->value,
            TripStatus::Accepted->value,
            TripStatus::Arriving->value,
            TripStatus::Waiting->value,
            TripStatus::InProgress->value,
        ];

        return DriverProfile::query()
            ->join('users', 'users.id', '=', 'driver_profiles.user_id')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'driver_profiles.user_id')
            ->where('driver_profiles.is_online', true)
            ->where('users.status', 'approved')
            ->where('users.role', 'driver')
            ->where('driver_profiles.vehicle_type', $trip->vehicle_type->value)
            ->when($trip->city_id !== null, function ($query) use ($trip): void {
                $query->where('driver_profiles.city_id', $trip->city_id);
            })
            ->where(function ($query): void {
                $query->whereNull('driver_profiles.blocked_until')
                    ->orWhere('driver_profiles.blocked_until', '<=', now());
            })
            ->whereNotNull('driver_profiles.last_location')
            ->where('driver_profiles.last_location_at', '>=', now()->subMinutes(2))
            ->whereRaw('coalesce(wallets.balance, 0) >= ?', [$tope])
            ->whereRaw(
                'ST_DWithin(driver_profiles.last_location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                [$lng, $lat, $radioKm * 1000],
            )
            ->whereNotExists(function ($query) use ($estadosOcupado): void {
                $query->selectRaw('1')
                    ->from('trips')
                    ->whereColumn('trips.driver_id', 'driver_profiles.user_id')
                    ->whereIn('trips.status', $estadosOcupado);
            })
            ->whereNotIn(
                'driver_profiles.user_id',
                TripOffer::query()
                    ->select('driver_id')
                    ->where('trip_id', $trip->getKey()),
            )
            ->when($soloFavoritos, function ($query) use ($trip): void {
                $query->whereExists(function ($subconsulta) use ($trip): void {
                    $subconsulta->selectRaw('1')
                        ->from('driver_favorites')
                        ->whereColumn('driver_favorites.driver_id', 'driver_profiles.user_id')
                        ->where('driver_favorites.passenger_id', $trip->passenger_id);
                });
            })
            ->select('driver_profiles.*')
            ->selectRaw(
                'ST_Distance(driver_profiles.last_location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) as distance_m',
                [$lng, $lat],
            )
            ->with('user')
            ->orderBy('distance_m')
            ->first();
    }

    /** @return array<int, float> */
    private function radios(): array
    {
        $radios = Configuracion::json('radios_km', [3, 5, 8]);

        if ($radios === []) {
            $radios = [Configuracion::decimal('radio_inicial_km', 3)];
        }

        $limpios = [];

        foreach ($radios as $radio) {
            $valor = round((float) $radio, 2);

            if ($valor > 0 && ! in_array($valor, $limpios, true)) {
                $limpios[] = $valor;
            }
        }

        return $limpios;
    }

    private function penalizar(?User $conductor): void
    {
        if ($conductor === null) {
            return;
        }

        $perfil = DriverProfile::query()
            ->where('user_id', $conductor->getKey())
            ->lockForUpdate()
            ->first();

        if ($perfil === null) {
            return;
        }

        $ventana = max(1, Configuracion::integer('ventana_rechazos_minutos', 60));
        $limite = max(1, Configuracion::integer('rechazos_para_bloqueo', 3));
        $duracion = max(1, Configuracion::integer('duracion_bloqueo_minutos', 60));
        $ahora = now();

        $inicio = $perfil->rejection_window_started_at;

        if ($inicio === null || $inicio->lt($ahora->copy()->subMinutes($ventana))) {
            $perfil->rejections_in_window = 1;
            $perfil->rejection_window_started_at = $ahora;
        } else {
            $perfil->rejections_in_window = (int) $perfil->rejections_in_window + 1;
        }

        $perfil->trips_rejected = (int) $perfil->trips_rejected + 1;

        if ($perfil->rejections_in_window >= $limite) {
            $perfil->blocked_until = $ahora->copy()->addMinutes($duracion);
            $perfil->blocked_reason = 'Bloqueo automático por rechazar o dejar vencer varios viajes.';
            $perfil->rejections_in_window = 0;
            $perfil->rejection_window_started_at = null;
            $perfil->is_online = false;
        }

        $perfil->save();

        if ($perfil->wasChanged('blocked_until') && $perfil->blocked_until !== null) {
            $this->notificar->ejecutar(
                'conductor_bloqueado',
                'Conductor bloqueado automáticamente',
                "{$conductor->name} fue bloqueado hasta {$perfil->blocked_until->format('d/m/Y H:i')} por rechazar o dejar vencer varios viajes.",
                route('panel.conductores.index'),
                [
                    'driver_id' => $conductor->id,
                    'blocked_until' => $perfil->blocked_until->toIso8601String(),
                ],
            );
        }
    }

    private function liberarHold(Trip $trip): void
    {
        $monto = (float) $trip->wallet_hold_amount;

        if ($monto <= 0) {
            return;
        }

        $this->billetera->liberarRetencion(
            $this->billetera->paraUsuario($trip->passenger),
            $monto,
        );

        $trip->forceFill(['wallet_hold_amount' => 0])->save();
    }
}
