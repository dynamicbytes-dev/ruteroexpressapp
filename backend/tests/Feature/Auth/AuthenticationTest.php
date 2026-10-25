<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autenticación del panel web (solo auditores).
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_acceso_se_muestra(): void
    {
        $this->get('/panel/login')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Panel exclusivo para auditores');
    }

    public function test_el_auditor_entra_con_su_correo_y_contrasena(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($auditor);
    }

    public function test_el_auditor_entra_aunque_el_correo_este_en_mayusculas_o_con_espacios(): void
    {
        $auditor = User::factory()->auditor()->create([
            'email' => 'auditor@gmail.com',
            'password' => 'password',
        ]);

        $this->post('/panel/login', [
            'email' => '  AUDITOR@GMAIL.COM  ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($auditor);
    }

    public function test_no_entra_con_contrasena_incorrecta(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_la_sesion_se_cierra(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->post('/panel/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
