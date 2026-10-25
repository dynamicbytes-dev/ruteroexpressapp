<?php

namespace Database\Factories;

use App\Enums\TripOfferStatus;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripOffer>
 */
class TripOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'driver_id' => User::factory()->driver()->approved(),
            'status' => TripOfferStatus::Pending,
            'distance_to_pickup_km' => 1.20,
            'offered_at' => now(),
            'expires_at' => now()->addSeconds(20),
        ];
    }
}
