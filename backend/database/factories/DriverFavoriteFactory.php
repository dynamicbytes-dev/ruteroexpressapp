<?php

namespace Database\Factories;

use App\Models\DriverFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DriverFavorite>
 */
class DriverFavoriteFactory extends Factory
{
    protected $model = DriverFavorite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'passenger_id' => User::factory()->passenger(),
            'driver_id' => User::factory()->driver(),
        ];
    }
}
