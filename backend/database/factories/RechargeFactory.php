<?php

namespace Database\Factories;

use App\Enums\RechargeStatus;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recharge>
 */
class RechargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->approved(),
            'amount_bs' => 4000,
            'bank' => 'Banesco',
            'reference' => (string) fake()->unique()->numberBetween(100000, 999999),
            'transferred_at' => now()->toDateString(),
            'proof_path' => 'comprobantes/recargas/'.fake()->uuid().'.jpg',
            'proof_disk' => 'local',
            'proof_original_name' => 'comprobante.jpg',
            'proof_mime_type' => 'image/jpeg',
            'proof_size_bytes' => fake()->numberBetween(100_000, 2_000_000),
            'status' => RechargeStatus::Pending,
        ];
    }

    public function approved(float $rate = 40, ?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RechargeStatus::Approved,
            'exchange_rate' => $rate,
            'amount_usd' => round(((float) $attributes['amount_bs']) / $rate, 2),
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $motivo = 'El comprobante no coincide con el monto.', ?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RechargeStatus::Rejected,
            'rejection_reason' => $motivo,
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }
}
