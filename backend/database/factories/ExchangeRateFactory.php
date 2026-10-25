<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate' => 40,
            'effective_date' => today()->toDateString(),
            'created_by' => null,
        ];
    }

    public function para(string $fecha, float $tasa): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $tasa,
            'effective_date' => $fecha,
        ]);
    }
}
