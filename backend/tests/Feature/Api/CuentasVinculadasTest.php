<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una persona puede tener cuenta de pasajero y de conductor con la misma
 * cédula. El cambio de cuenta dentro de la app exige vincularlas una vez con
 * la contraseña de la otra cuenta.
 */
class CuentasVinculadasTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $extra */
    private function datosRegistro(string $rol, string $email, string $cedula, array $extra = []): array
    {
        return array_merge([
            'role' => $rol,
            'first_name' => 'María',
            'last_name' => 'Pérez',
            'national_id' => $cedula,
            'email' => $email,
            'phone' => '0412-1234567',
            'address' => 'Av. Bolívar, casa 12, Maracay',
            'city_id' => City::defaultId(),
            'password' => 'clave12345',
            'password_confirmation' => 'clave12345',
            'accept_terms' => true,
            'terms_version' => config('legal.terms_version'),
        ], $extra);
    }

    private function pasajero(): User
    {
        return User::factory()->passenger()->create([
            'national_id' => '12345678',
            'email' => 'pasajero@gmail.com',
            'password' => 'clave12345',
        ]);
    }

    private function conductor(array $extra = []): User
    {
        return User::factory()->driver()->approved()->create(array_merge([
            'national_id' => '12345678',
            'email' => 'conductor@gmail.com',
            'password' => 'clave12345',
        ], $extra));
    }

    public function test_la_misma_cedula_puede_tener_cuenta_de_pasajero_y_conductor(): void
    {
        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'pasajero@gmail.com',
            '12345678',
        ))->assertCreated();

        $this->postJson('/api/auth/register', $this->datosRegistro(
            'driver',
            'conductor@gmail.com',
            '12345678',
            [
                'vehicle_type' => 'moto',
                'vehicle_brand' => 'Bera',
                'vehicle_model' => 'BR 150',
                'vehicle_year' => 2022,
                'vehicle_plate' => 'AB123CD',
            ],
        ))->assertCreated();

        $this->assertSame(2, User::query()->where('national_id', '12345678')->count());
    }

    public function test_no_se_puede_repetir_la_cedula_dentro_del_mismo_tipo_de_cuenta(): void
    {
        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'pasajero@gmail.com',
            '12345678',
        ))->assertCreated();

        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'otro@gmail.com',
            '12345678',
        ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('national_id');
    }

    public function test_el_mismo_correo_puede_tener_cuenta_de_pasajero_y_conductor(): void
    {
        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'comun@gmail.com',
            '12345678',
        ))->assertCreated();

        $this->postJson('/api/auth/register', $this->datosRegistro(
            'driver',
            'comun@gmail.com',
            '12345678',
            [
                'vehicle_type' => 'moto',
                'vehicle_brand' => 'Bera',
                'vehicle_model' => 'BR 150',
                'vehicle_year' => 2022,
                'vehicle_plate' => 'AB123CD',
            ],
        ))->assertCreated();

        $this->assertSame(2, User::query()->where('email', 'comun@gmail.com')->count());
    }

    public function test_no_se_puede_repetir_el_correo_dentro_del_mismo_tipo_de_cuenta(): void
    {
        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'comun@gmail.com',
            '12345678',
        ))->assertCreated();

        $this->postJson('/api/auth/register', $this->datosRegistro(
            'passenger',
            'comun@gmail.com',
            '87654321',
        ))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_con_mismo_correo_permite_especificar_rol_o_resuelve_por_contrasena(): void
    {
        $pasajero = User::factory()->passenger()->create([
            'national_id' => '12345678',
            'email' => 'comun@gmail.com',
            'password' => 'clavePasajero1',
        ]);

        $conductor = User::factory()->driver()->approved()->create([
            'national_id' => '12345678',
            'email' => 'comun@gmail.com',
            'password' => 'claveConductor2',
        ]);

        // Login sin rol pero con password del conductor
        $this->postJson('/api/auth/login', [
            'email' => 'comun@gmail.com',
            'password' => 'claveConductor2',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $conductor->id);

        // Login sin rol pero con password del pasajero
        $this->postJson('/api/auth/login', [
            'email' => 'comun@gmail.com',
            'password' => 'clavePasajero1',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $pasajero->id);

        // Login indicando rol explícito
        $this->postJson('/api/auth/login', [
            'email' => 'comun@gmail.com',
            'password' => 'claveConductor2',
            'role' => 'driver',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $conductor->id);
    }
}
