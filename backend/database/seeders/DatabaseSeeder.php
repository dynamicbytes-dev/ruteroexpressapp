<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Auditor inicial para entrar al panel en desarrollo.
        // En producción se crea con: php artisan motoruta:crear-auditor
        $auditor = User::query()->firstOrNew(['email' => 'auditor@motoruta.app']);
        $hayPrincipal = User::query()->where('is_owner', true)->exists();

        if (! $auditor->exists) {
            // Nunca se usa una contraseña compartida ni se escribe en el código.
            // En desarrollo se puede fijar AUDITOR_INITIAL_PASSWORD; si no, se
            // genera una y se guarda fuera de git para que puedas iniciar sesión.
            $plainPassword = (string) env('AUDITOR_INITIAL_PASSWORD', '');

            if (strlen($plainPassword) < 12) {
                $plainPassword = Str::password(20);

                $ruta = storage_path('auditor-password.txt');
                file_put_contents($ruta, $plainPassword.PHP_EOL);
                @chmod($ruta, 0600);

                $this->command?->warn("Contraseña inicial del auditor generada en: {$ruta}");
            }

            $auditor->fill([
                'role' => UserRole::Auditor,
                'is_owner' => ! $hayPrincipal,
                'first_name' => 'Auditor',
                'last_name' => 'Express',
                'national_id' => '00000001',
                'phone' => '04120000000',
                'address' => 'Oficina GAZ',
                'password' => Hash::make($plainPassword),
                'status' => UserStatus::Approved,
                'email_verified_at' => now(),
                'approved_at' => now(),
            ])->save();
        } elseif (! $hayPrincipal) {
            // Instalaciones anteriores a la distinción de auditor principal.
            $auditor->forceFill(['is_owner' => true])->save();
        }

        $this->call([
            PermissionSeeder::class,
            SettingsSeeder::class,
            ExchangeRateSeeder::class,
        ]);
    }
}
