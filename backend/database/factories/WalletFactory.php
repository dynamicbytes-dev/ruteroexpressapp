<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => 0,
            'held_balance' => 0,
        ];
    }

    public function saldo(float $monto): static
    {
        return $this->state(fn (array $attributes) => ['balance' => $monto]);
    }

    public function retenido(float $monto): static
    {
        return $this->state(fn (array $attributes) => ['held_balance' => $monto]);
    }
}
