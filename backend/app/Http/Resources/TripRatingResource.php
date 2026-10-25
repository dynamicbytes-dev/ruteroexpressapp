<?php

namespace App\Http\Resources;

use App\Models\TripRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Calificación vista por el pasajero que la dejó o por el conductor que la
 * recibió. Nunca incluye el nombre del pasajero.
 *
 * @mixin TripRating
 */
class TripRatingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rating' => (int) $this->rating,
            'stars' => $this->stars(),
            'comment' => $this->comment,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
