<?php

namespace App\Domain\Trips;

use App\Domain\Wallet\WalletService;
use App\Enums\TripStatus;
use App\Events\UbicacionConductorActualizada;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Support\Configuracion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Disponibilidad y ubicación del conductor.
 *
 * Solo un conductor aprobado, sin bloqueo y con una posición reciente puede
 * recibir alertas. Si su saldo bajó del tope negativo, primero debe recargar.
 */
class DriverService
{
    public function __construct(
        private readonly WalletService $billetera,
        private readonly TripDispatcher $asignador,
    ) {}

    public function actualizarDisponibilidad(User $conductor, bool $enLinea): DriverProfile
    {
        $perfil = $this->perfil($conductor);

        if ($enLinea) {
            if (! $conductor->canOperate()) {
                throw new RuntimeException('Tu cuenta todavía no está habilitada para conectarte.');
            }

            if ($perfil->isBlocked()) {
                throw new RuntimeException(
                    'Estás bloqueado hasta '.$perfil->blocked_until->format('d/m/Y H:i').'.',
                );
            }

            if ($perfil->hasDebtOverCap()) {
                throw new RuntimeException('Tu saldo superó el tope negativo. Recarga para volver a conectarte.');
            }

            if (! $perfil->hasFreshLocation()) {
                throw new RuntimeException('Primero comparte tu ubicación para poder conectarte.');
            }
        }

        $perfil->forceFill([
            'is_online' => $enLinea,
            'went_online_at' => $enLinea ? ($perfil->went_online_at ?? now()) : null,
        ])->save();

        if (! $enLinea) {
            $this->asignador->cancelarOfertaPendienteDeConductor($conductor);
        }

        return $perfil->refresh();
    }

    public function actualizarUbicacion(
        User $conductor,
        float $lat,
        float $lng,
        ?int $rumbo = null,
        ?float $velocidad = null,
    ): DriverProfile {
        $perfil = $this->perfil($conductor);

        if (! $conductor->canOperate()) {
            throw new RuntimeException('Tu cuenta todavía no está habilitada para reportar ubicación.');
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new RuntimeException('Las coordenadas no son válidas.');
        }

        $ahora = now();

        DB::statement(
            'update driver_profiles
             set last_location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                 last_speed_kmh = ?,
                 last_bearing = ?,
                 last_location_at = ?,
                 updated_at = ?
             where id = ?',
            [$lng, $lat, $velocidad, $rumbo, $ahora, $ahora, $perfil->getKey()],
        );

        $viaje = Trip::query()
            ->where('driver_id', $conductor->getKey())
            ->whereIn(
                'status',
                array_map(fn (TripStatus $estado): string => $estado->value, TripStatus::assigned()),
            )
            ->latest()
            ->first();

        if ($viaje !== null) {
            UbicacionConductorActualizada::dispatch(
                $viaje,
                $lat,
                $lng,
                $rumbo,
                $velocidad,
                $ahora,
            );
        }

        return $perfil->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(User $conductor): array
    {
        $perfil = $this->perfil($conductor);
        $billetera = $this->billetera->paraUsuario($conductor);

        return [
            'online' => $perfil->is_online,
            'available' => $perfil->isAvailable(),
            'blocked_until' => $perfil->blocked_until?->toIso8601String(),
            'blocked_reason' => $perfil->blocked_reason,
            'rejections_in_window' => (int) $perfil->rejections_in_window,
            'rejections_before_block' => Configuracion::integer('rechazos_para_bloqueo', 3),
            'trips_completed' => (int) $perfil->trips_completed,
            'rating' => $perfil->ratingOrNull(),
            'ratings_count' => (int) $perfil->ratings_count,
            'balance' => (float) $billetera->balance,
            'available_balance' => $this->billetera->disponible($billetera),
            'negative_cap' => Configuracion::decimal('tope_saldo_negativo', -5),
            'location' => $perfil->coordinates(),
            'location_updated_at' => $perfil->last_location_at?->toIso8601String(),
        ];
    }

    public function viajeActivo(User $conductor): ?Trip
    {
        return Trip::query()
            ->forDriver($conductor)
            ->active()
            ->with(['passenger', 'driver.driverProfile', 'currentOffer', 'rating', 'city'])
            ->latest()
            ->first();
    }

    /** @return array<int, TripOffer> */
    public function ofertasPendientes(User $conductor): array
    {
        $this->asignador->expirarVencidas();

        return TripOffer::query()
            ->where('driver_id', $conductor->getKey())
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['trip.passenger'])
            ->orderBy('expires_at')
            ->get()
            ->all();
    }

    private function perfil(User $conductor): DriverProfile
    {
        if (! $conductor->isDriver()) {
            throw new RuntimeException('Solo los conductores tienen un perfil de viaje.');
        }

        $perfil = DriverProfile::query()->where('user_id', $conductor->getKey())->first();

        if ($perfil === null) {
            throw new RuntimeException('Tu perfil de conductor no está completo.');
        }

        return $perfil;
    }
}
