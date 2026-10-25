<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'city_id' => City::defaultId(),
            'vehicle_type' => VehicleType::Motorcycle,
            'vehicle_brand' => fake()->randomElement(['Bera', 'Empire', 'Keeway', 'Yamaha', 'Honda', 'Torino']),
            'vehicle_model' => fake()->randomElement(['BR 150', 'Owen 150', 'K-Light 150', 'XTZ 125', 'Storm 200', 'Carlo 150']),
            'vehicle_year' => fake()->numberBetween(2015, (int) date('Y')),
            'vehicle_plate' => strtoupper(fake()->unique()->bothify('AB###??')),
            'is_online' => false,
        ];
    }

    /** Ubica al conductor para que pueda recibir alertas en las pruebas. */
    public function locatedAt(float $lat, float $lng): static
    {
        return $this->afterCreating(function (DriverProfile $perfil) use ($lat, $lng): void {
            $ahora = now();

            DB::statement(
                'update driver_profiles
                 set last_location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                     last_location_at = ?,
                     is_online = true,
                     went_online_at = ?
                 where id = ?',
                [$lng, $lat, $ahora, $ahora, $perfil->getKey()],
            );

            $perfil->refresh();
        });
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'went_online_at' => now(),
        ]);
    }

    public function blocked(int $minutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_until' => now()->addMinutes($minutes),
            'blocked_reason' => 'Excedió el número de rechazos permitidos.',
            'is_online' => false,
        ]);
    }
}
