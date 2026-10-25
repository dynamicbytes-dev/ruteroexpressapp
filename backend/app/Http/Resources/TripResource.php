<?php

namespace App\Http\Resources;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Viaje visto por el pasajero o el conductor asignado.
 *
 * El teléfono de la otra parte solo aparece cuando el viaje ya está asignado:
 * antes de aceptar, el conductor ve el nombre del pasajero y el punto de
 * recogida, pero no sus datos de contacto.
 *
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $usuario = $request->user();
        $esPasajero = $usuario !== null && $this->passenger_id === $usuario->getKey();
        $esConductor = $usuario !== null && $this->driver_id === $usuario->getKey();

        $conductor = null;

        if ($this->driver !== null
            && ($esConductor
                || ($esPasajero && ($this->status->isAssigned() || $this->status === TripStatus::Completed)))) {
            $perfil = $this->driver->driverProfile;
            $ubicacion = $perfil?->coordinates();

            $conductor = [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
                'rating' => $perfil === null ? null : $perfil->ratingOrNull(),
                'ratings_count' => $perfil === null ? 0 : (int) $perfil->ratings_count,
                'trips_completed' => $perfil === null ? null : (int) $perfil->trips_completed,
                'vehicle' => $perfil?->vehicleDescription(),
                'is_favorite' => $esPasajero && in_array($this->driver_id, $this->favoritos($request), true),
                'location' => $ubicacion,
                'location_updated_at' => $perfil?->last_location_at?->toIso8601String(),
                'avatar' => $this->driver->avatarUrl() ?? $this->driver->photoUrl(),
                'photo_url' => $this->driver->avatarUrl() ?? $this->driver->photoUrl(),
            ];
        }

        $pasajero = null;

        if ($esConductor && $this->passenger !== null) {
            $pasajero = [
                'id' => $this->passenger->id,
                'name' => $this->passenger->name,
                'phone' => $this->passenger->phone,
                'avatar' => $this->passenger->avatarUrl() ?? $this->passenger->photoUrl(),
                'photo_url' => $this->passenger->avatarUrl() ?? $this->passenger->photoUrl(),
            ];
        }

        $oferta = null;

        if ($esConductor && $this->currentOffer !== null && $this->currentOffer->driver_id === $usuario->getKey()) {
            $oferta = TripOfferResource::make($this->currentOffer)->resolve($request);
        }

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'city' => $this->city === null ? null : [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'state' => $this->city->state,
            ],
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'vehicle_type' => $this->vehicle_type->value,
            'vehicle_type_label' => $this->vehicle_type->label(),
            'origin' => [
                'address' => $this->origin_address,
                'lat' => (float) $this->origin_lat,
                'lng' => (float) $this->origin_lng,
            ],
            'destination' => [
                'address' => $this->destination_address,
                'lat' => (float) $this->destination_lat,
                'lng' => (float) $this->destination_lng,
            ],
            'distance_km' => (float) $this->distance_km,
            'duration_minutes' => (int) $this->duration_minutes,
            'fare' => [
                'base' => (float) $this->base_fare,
                'price_per_km' => (float) $this->price_per_km,
                'price_per_minute' => (float) $this->price_per_minute,
                'minimum' => (float) $this->minimum_fare,
                'minimum_applied' => (bool) $this->minimum_applied,
                'dynamic_multiplier' => (float) $this->dynamic_multiplier,
                'night_surcharge_percent' => (float) $this->night_surcharge_percent,
                'subtotal' => (float) $this->subtotal,
                'vehicle_surcharge' => (float) data_get($this->fare_breakdown, 'vehicle_surcharge', 0),
                'total' => (float) $this->total,
                'discount_percent' => $this->discount_percent === null ? null : (float) $this->discount_percent,
                'discount_amount' => (float) $this->discount_amount,
                'subsidy_amount' => (float) $this->subsidy_amount,
                'promo_type' => $this->promo_type,
                'passenger_pays' => round((float) $this->total - (float) $this->discount_amount, 2),
                'commission_percent' => (float) $this->commission_percent,
                'commission_amount' => (float) $this->commission_amount,
                'driver_earning' => (float) $this->driver_earning,
                'exchange_rate' => $this->exchange_rate === null ? null : (float) $this->exchange_rate,
                'local_total' => $this->exchange_rate === null
                    ? null
                    : round((float) $this->total * (float) $this->exchange_rate, 2),
                'breakdown' => $this->fare_breakdown,
            ],
            'driver' => $conductor,
            'passenger' => $pasajero,
            'current_offer' => $oferta,
            'rating' => $this->rating === null ? null : TripRatingResource::make($this->rating)->resolve($request),
            'can_rate' => $this->canBeRatedBy($usuario),
            'can_cancel' => $this->canBeCancelled()
                && ($esPasajero || ($esConductor && $this->status !== TripStatus::InProgress)),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }

    /**
     * IDs de los conductores favoritos del pasajero autenticado.
     *
     * Se cachea en la petición: el historial pinta hasta 20 viajes y no debe
     * disparar una consulta por cada uno.
     *
     * @return array<int, int>
     */
    private function favoritos(Request $request): array
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return [];
        }

        $ids = $request->attributes->get('_favorite_driver_ids');

        if ($ids === null) {
            $ids = $usuario->favoriteDrivers()->pluck('users.id')->all();
            $request->attributes->set('_favorite_driver_ids', $ids);
        }

        return $ids;
    }
}
