<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crea una cuenta de auditor. Es la única forma de crear auditores:
 * no existe registro público para este rol.
 *
 * La primera cuenta creada es el auditor principal (clase A), dueño del
 * sistema. Las siguientes quedan como auditores secundarios.
 */
class CrearAuditor extends Command
{
    protected $signature = 'motoruta:crear-auditor
                            {--email= : Correo del auditor}
                            {--nombre= : Nombre}
                            {--apellido= : Apellido}
                            {--cedula= : Cédula}';

    protected $description = 'Crea (o promueve) una cuenta de auditor para el panel web';

    public function handle(): int
    {
        $email = $this->option('email') ?: text(
            label: 'Correo del auditor',
            required: true,
            validate: fn (string $value) => Validator::make(
                ['email' => $value],
                ['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')]],
            )->errors()->first('email') ?: null,
        );

        $firstName = $this->option('nombre') ?: text(label: 'Nombre', required: true);
        $lastName = $this->option('apellido') ?: text(label: 'Apellido', required: true);
        $nationalId = $this->option('cedula') ?: text(
            label: 'Cédula (sin puntos)',
            required: true,
            validate: fn (string $value) => preg_match('/^\d{6,9}$/', $value)
                ? (User::withTrashed()->where('national_id', $value)->exists()
                    ? 'Esa cédula ya está registrada.'
                    : null)
                : 'La cédula debe tener entre 6 y 9 dígitos, sin puntos.',
        );

        $plainPassword = password(
            label: 'Contraseña',
            required: true,
            validate: fn (string $value) => strlen($value) >= 12
                ? null
                : 'Por seguridad, la contraseña del auditor debe tener al menos 12 caracteres.',
        );

        $esPrincipal = ! User::query()->where('is_owner', true)->exists();

        $auditor = User::query()->create([
            'role' => UserRole::Auditor,
            'is_owner' => $esPrincipal,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'national_id' => $nationalId,
            'email' => $email,
            'address' => 'Oficina GAZ',
            'password' => Hash::make($plainPassword),
            'status' => UserStatus::Approved,
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $auditor->assignRole('auditor');

        $this->info("Auditor creado: {$auditor->email}");

        if ($esPrincipal) {
            $this->line('Esta cuenta es el auditor principal (clase A): solo ella puede crear otros auditores.');
        }

        $this->line('Ya puede entrar al panel en '.url('/panel'));

        return self::SUCCESS;
    }
}
