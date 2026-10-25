<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->uppercase(fake()->bothify('???###')),
            'discount_percent' => fake()->numberBetween(10, 50),
            'min_purchase' => null,
            'max_discount' => null,
            'valid_from' => today(),
            'valid_until' => today()->addDays(30),
            'max_uses_global' => null,
            'max_uses_per_user' => null,
            'city_id' => City::defaultId(),
            'created_by' => User::query()->where('role', 'auditor')->first()?->getKey() ?? 1,
            'is_active' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function vencido(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => today()->subDays(1),
        ]);
    }

    public function sinVencimiento(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => null,
        ]);
    }

    public function conLimiteGlobal(int $limite): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses_global' => $limite,
        ]);
    }

    public function conLimitePorUsuario(int $limite): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses_per_user' => $limite,
        ]);
    }
}
