<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDocument>
 */
class UserDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => DocumentType::NationalId,
            'status' => DocumentStatus::Pending,
            'file_path' => 'documentos/'.fake()->uuid().'.jpg',
            'disk' => 'local',
            'original_name' => 'documento.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(200_000, 4_000_000),
            'uploaded_at' => now(),
        ];
    }

    public function ofType(DocumentType $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type]);
    }

    public function approved(?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'No se lee el número de cédula.', ?User $auditor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $auditor?->getKey(),
            'reviewed_at' => now(),
        ]);
    }
}
