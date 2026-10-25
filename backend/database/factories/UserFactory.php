<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => UserRole::Passenger,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            // Cédula venezolana: entre 6 y 9 dígitos, sin puntos.
            'national_id' => (string) fake()->unique()->numberBetween(1_000_000, 32_000_000),
            'email' => fake()->unique()->userName().'@gmail.com',
            'phone' => '04'.fake()->numerify('#########'),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'city_id' => City::defaultId(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Pending,
            'submitted_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function auditor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Auditor,
            'is_owner' => false,
            'status' => UserStatus::Approved,
            'approved_at' => now(),
            'address' => 'Oficina GAZ',
        ])->afterCreating(function (User $user): void {
            // El rol de Spatie solo separa los permisos finos del panel; el
            // rol funcional sigue siendo `users.role`.
            $user->assignRole('auditor');
        });
    }

    /** Auditor principal (clase A): único que puede crear otros auditores. */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['is_owner' => true]);
    }

    public function passenger(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Passenger]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Driver]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'La foto de la cédula está borrosa.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Rejected,
            'status_reason' => $reason,
        ]);
    }
}
