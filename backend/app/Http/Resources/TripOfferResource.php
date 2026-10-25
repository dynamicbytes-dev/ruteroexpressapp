<?php

namespace App\Http\Resources;

use App\Models\TripOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerta de viaje que ve el conductor. El teléfono del pasajero no se incluye:
 * recién se comparte cuando el conductor acepta y el viaje queda asignado.
 *
 * @mixin TripOffer
 */
class TripOfferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viaje = $this->trip;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'distance_to_pickup_km' => (float) $this->distance_to_pickup_km,
            'offered_at' => $this->offered_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'seconds_left' => $this->secondsLeft(),
            'trip' => $viaje === null ? null : [
                'id' => $viaje->id,
                'passenger_name' => $viaje->passenger?->name,
                'origin' => [
                    'address' => $viaje->origin_address,
                    'lat' => (float) $viaje->origin_lat,
                    'lng' => (float) $viaje->origin_lng,
                ],
                'destination' => [
                    'address' => $viaje->destination_address,
                    'lat' => (float) $viaje->destination_lat,
                    'lng' => (float) $viaje->destination_lng,
                ],
                'distance_km' => (float) $viaje->distance_km,
                'duration_minutes' => (int) $viaje->duration_minutes,
                'payment_method' => $viaje->payment_method->value,
                'payment_method_label' => $viaje->payment_method->label(),
                'total' => (float) $viaje->total,
                'driver_earning' => (float) $viaje->driver_earning,
                'commission_amount' => (float) $viaje->commission_amount,
            ],
        ];
    }
}
