<?php

namespace App\Domain\Trips;

use App\Enums\TripStatus;
use App\Models\DriverFavorite;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Conductores favoritos de un pasajero.
 *
 * Solo se puede marcar a un conductor con el que ya se completó un viaje: la
 * app lo ofrece al calificar o desde la tarjeta del conductor.
 */
class DriverFavoriteService
{
    public function esFavorito(User $pasajero, int $conductorId): bool
    {
        return DriverFavorite::query()
            ->where('passenger_id', $pasajero->getKey())
            ->where('driver_id', $conductorId)
            ->exists();
    }

    public function marcar(User $pasajero, User $conductor): DriverFavorite
    {
        if ($pasajero->getKey() === $conductor->getKey()) {
            throw new RuntimeException('No puedes marcarte como conductor favorito.');
        }

        if (! $conductor->isDriver()) {
            throw new RuntimeException('Ese usuario no es un conductor.');
        }

        if (! $this->tuvoViajeCompletado($pasajero, $conductor)) {
            throw new RuntimeException('Solo puedes marcar como favorito a un conductor con el que ya completaste un viaje.');
        }

        return DriverFavorite::query()->firstOrCreate([
            'passenger_id' => $pasajero->getKey(),
            'driver_id' => $conductor->getKey(),
        ]);
    }

    public function quitar(User $pasajero, User $conductor): void
    {
        DriverFavorite::query()
            ->where('passenger_id', $pasajero->getKey())
            ->where('driver_id', $conductor->getKey())
            ->delete();
    }

    /** @return Collection<int, User> */
    public function conductores(User $pasajero): Collection
    {
        return $pasajero->favoriteDrivers()
            ->with('driverProfile')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    public function tuvoViajeCompletado(User $pasajero, User $conductor): bool
    {
        return Trip::query()
            ->where('passenger_id', $pasajero->getKey())
            ->where('driver_id', $conductor->getKey())
            ->where('status', TripStatus::Completed)
            ->exists();
    }
}
