<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver()->approved(),
            'amount_usd' => 5,
            'bank' => 'Banesco',
            'account_number' => '01340000000000000000',
            'account_holder' => fake()->name(),
            'account_id' => (string) fake()->numberBetween(10_000_000, 30_000_000),
            'status' => WithdrawalStatus::Pending,
        ];
    }

    public function paid(?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WithdrawalStatus::Paid,
            'proof_path' => 'comprobantes/retiros/'.fake()->uuid().'.jpg',
            'proof_original_name' => 'transferencia.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size_bytes' => fake()->numberBetween(100_000, 2_000_000),
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $motivo = 'La cuenta destino no está a nombre del conductor.', ?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WithdrawalStatus::Rejected,
            'rejection_reason' => $motivo,
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }
}
