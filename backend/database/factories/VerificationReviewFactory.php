<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VerificationReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationReview>
 */
class VerificationReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auditor_id' => User::factory()->auditor(),
            'scope' => VerificationReview::SCOPE_DOCUMENT,
            'decision' => VerificationReview::DECISION_APPROVED,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
