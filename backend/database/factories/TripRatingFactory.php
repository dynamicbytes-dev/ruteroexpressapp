<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripRating>
 */
class TripRatingFactory extends Factory
{
    protected $model = TripRating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'passenger_id' => User::factory()->passenger(),
            'driver_id' => User::factory()->driver(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => null,
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (): array => [
            'trip_id' => $trip->getKey(),
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);
    }

    public function rating(int $estrellas): static
    {
        return $this->state(fn (): array => ['rating' => $estrellas]);
    }
}
