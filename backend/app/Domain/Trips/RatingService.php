<?php

namespace App\Domain\Trips;

use App\Enums\TripStatus;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\TripRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Calificación del pasajero al conductor.
 *
 * Un viaje completado se califica una sola vez. El promedio se recalcula en
 * `driver_profiles` para que la app y la cascada lo lean sin agregar filas.
 */
class RatingService
{
    public function __construct(private readonly DriverFavoriteService $favoritos) {}

    /**
     * @param  array{rating: int, comment?: string|null, favorite?: bool}  $datos
     */
    public function calificar(Trip $viaje, User $pasajero, array $datos): Trip
    {
        return DB::transaction(function () use ($viaje, $pasajero, $datos): Trip {
            /** @var Trip $viaje */
            $viaje = Trip::query()->whereKey($viaje->getKey())->lockForUpdate()->firstOrFail();

            if ($viaje->passenger_id !== $pasajero->getKey()) {
                throw new RuntimeException('Solo el pasajero del viaje puede calificarlo.');
            }

            if ($viaje->status !== TripStatus::Completed) {
                throw new RuntimeException('Solo puedes calificar un viaje completado.');
            }

            if ($viaje->driver_id === null) {
                throw new RuntimeException('Este viaje no tiene conductor asignado.');
            }

            if (TripRating::query()->where('trip_id', $viaje->getKey())->exists()) {
                throw new RuntimeException('Ya calificaste este viaje.');
            }

            TripRating::create([
                'trip_id' => $viaje->getKey(),
                'passenger_id' => $pasajero->getKey(),
                'driver_id' => $viaje->driver_id,
                'rating' => (int) $datos['rating'],
                'comment' => $this->comentario($datos['comment'] ?? null),
            ]);

            $this->recalcular($viaje->driver_id);

            if (($datos['favorite'] ?? false) === true) {
                $conductor = User::query()->find($viaje->driver_id);

                if ($conductor !== null) {
                    $this->favoritos->marcar($pasajero, $conductor);
                }
            }

            return $viaje->refresh()->load(['passenger', 'driver.driverProfile', 'rating']);
        });
    }

    /** Últimas calificaciones recibidas por un conductor, para su panel. */
    /** @return Collection<int, TripRating> */
    public function recibidas(User $conductor, int $limite = 20): Collection
    {
        return TripRating::query()
            ->where('driver_id', $conductor->getKey())
            ->latest('created_at')
            ->limit($limite)
            ->get();
    }

    /** Recalcula promedio y cantidad desde la tabla, sin confiar en incrementos. */
    public function recalcular(int $conductorId): void
    {
        $resumen = TripRating::query()
            ->where('driver_id', $conductorId)
            ->selectRaw('count(*) as total, avg(rating) as promedio')
            ->first();

        DriverProfile::query()
            ->where('user_id', $conductorId)
            ->update([
                'ratings_count' => (int) ($resumen->total ?? 0),
                'rating_average' => round((float) ($resumen->promedio ?? 0), 2),
            ]);
    }

    private function comentario(?string $comentario): ?string
    {
        $comentario = trim((string) $comentario);

        return $comentario === '' ? null : $comentario;
    }
}
