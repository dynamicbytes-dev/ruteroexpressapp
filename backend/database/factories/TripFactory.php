<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\TripStatus;
use App\Models\City;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $distancia = 5.0;
        $duracion = 12;
        $base = 1.00;
        $porKm = 0.35;
        $porMinuto = 0.05;
        $subtotal = round($base + ($distancia * $porKm) + ($duracion * $porMinuto), 2);
        $total = round($subtotal, 2);
        $comision = round($total * 0.10, 2);

        return [
            'passenger_id' => User::factory()->passenger()->approved(),
            'driver_id' => null,
            'city_id' => City::defaultId(),
            'status' => TripStatus::Searching,
            'payment_method' => PaymentMethod::Wallet,
            'origin_address' => 'Av. Francisco de Miranda, Caracas',
            'destination_address' => 'Plaza Venezuela, Caracas',
            'origin_lat' => 10.4961000,
            'origin_lng' => -66.8517000,
            'destination_lat' => 10.5003000,
            'destination_lng' => -66.8428000,
            'distance_km' => $distancia,
            'duration_minutes' => $duracion,
            'base_fare' => $base,
            'price_per_km' => $porKm,
            'price_per_minute' => $porMinuto,
            'minimum_fare' => 1.50,
            'minimum_applied' => false,
            'dynamic_multiplier' => 1.00,
            'night_surcharge_percent' => 0,
            'subtotal' => $subtotal,
            'total' => $total,
            'commission_percent' => 10,
            'commission_amount' => $comision,
            'driver_earning' => round($total - $comision, 2),
            'exchange_rate' => 40,
            'fare_breakdown' => [
                'distance_km' => $distancia,
                'duration_minutes' => $duracion,
                'base_fare' => $base,
                'price_per_km' => $porKm,
                'price_per_minute' => $porMinuto,
                'minimum_fare' => 1.50,
                'minimum_applied' => false,
                'dynamic_multiplier' => 1.00,
                'night_surcharge_percent' => 0,
                'night' => false,
                'subtotal' => $subtotal,
                'total' => $total,
                'commission_percent' => 10,
                'commission_amount' => $comision,
                'driver_earning' => round($total - $comision, 2),
                'route_source' => 'estimada',
            ],
            'wallet_hold_amount' => 0,
            'search_radius_index' => 0,
            'requested_at' => now(),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::Cash,
        ]);
    }

    public function assignedTo(User $driver): static
    {
        return $this->state(fn (array $attributes) => [
            'driver_id' => $driver->getKey(),
            'status' => TripStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TripStatus::InProgress,
            'accepted_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
        ]);
    }
}
