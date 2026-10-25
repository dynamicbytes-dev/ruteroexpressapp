<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conductor favorito en la lista del pasajero.
 *
 * @mixin User
 */
class DriverFavoriteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $perfil = $this->driverProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'rating' => $perfil?->ratingOrNull(),
            'ratings_count' => $perfil === null ? 0 : (int) $perfil->ratings_count,
            'trips_completed' => $perfil === null ? 0 : (int) $perfil->trips_completed,
            'vehicle' => $perfil?->vehicleDescription(),
            'vehicle_type' => $perfil?->vehicle_type?->value,
            'is_online' => (bool) $perfil?->is_online,
            'favorited_at' => $this->pivot?->created_at?->toIso8601String(),
        ];
    }
}
