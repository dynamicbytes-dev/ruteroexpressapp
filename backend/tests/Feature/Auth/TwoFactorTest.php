<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function activar(User $auditor): array
    {
        $this->actingAs($auditor)->post('/panel/2fa')->assertRedirect('/panel/2fa');

        $auditor->refresh();
        $secreto = $auditor->two_factor_secret;
        $codigo = app('pragmarx.google2fa')->getCurrentOtp($secreto);

        $this->actingAs($auditor)
            ->post('/panel/2fa/confirmar', ['code' => $codigo])
            ->assertRedirect('/panel/2fa')
            ->assertSessionHasNoErrors();

        $auditor->refresh();

        return [$secreto, session('recovery_codes', [])];
    }

    public function test_el_auditor_activa_el_doble_factor(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->get('/panel/2fa')
            ->assertOk()
            ->assertSee('Activar doble factor');

        [$secreto, $codigos] = $this->activar($auditor);

        $this->assertTrue($auditor->hasEnabledTwoFactor());
        $this->assertNotNull($secreto);
        $this->assertCount(8, $codigos);

        $this->actingAs($auditor)
            ->get('/panel/2fa')
            ->assertOk()
            ->assertSee('Activo')
            ->assertSee('Regenerar códigos de recuperación');
    }

    public function test_el_auditor_confirma_con_un_codigo_invalido_y_no_se_activa(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->post('/panel/2fa');
        $auditor->refresh();

        $this->actingAs($auditor)
            ->from('/panel/2fa')
            ->post('/panel/2fa/confirmar', ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertRedirect('/panel/2fa');

        $this->assertFalse($auditor->refresh()->hasEnabledTwoFactor());
    }

    public function test_el_login_con_doble_factor_pide_el_codigo_antes_de_abrir_sesion(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);
        [$secreto] = $this->activar($auditor);
        $this->post('/panel/logout');

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'password',
        ])->assertRedirect(route('panel.2fa.challenge', absolute: false));

        $this->assertGuest();

        $this->get('/panel/2fa/desafio')
            ->assertOk()
            ->assertSee('Verificación en dos pasos')
            ->assertSee($auditor->email);

        $this->post('/panel/2fa/desafio', [
            'code' => app('pragmarx.google2fa')->getCurrentOtp($secreto),
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($auditor);
    }

    public function test_un_codigo_incorrecto_no_abre_la_sesion(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);
        $this->activar($auditor);
        $this->post('/panel/logout');

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'password',
        ]);

        $this->from('/panel/2fa/desafio')
            ->post('/panel/2fa/desafio', ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertRedirect('/panel/2fa/desafio');

        $this->assertGuest();
    }

    public function test_un_codigo_de_recuperacion_sirve_una_sola_vez(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);
        [, $codigos] = $this->activar($auditor);
        $this->post('/panel/logout');

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'password',
        ]);

        $this->post('/panel/2fa/desafio', ['code' => $codigos[0]])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($auditor);

        // Se cierra la sesión y el mismo código ya no debe servir.
        $this->post('/panel/logout');
        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'password',
        ]);

        $this->from('/panel/2fa/desafio')
            ->post('/panel/2fa/desafio', ['code' => $codigos[0]])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_el_auditor_desactiva_el_doble_factor_con_contrasena_y_codigo(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);
        [$secreto] = $this->activar($auditor);

        $this->actingAs($auditor)
            ->delete('/panel/2fa', [
                'password' => 'password',
                'code' => app('pragmarx.google2fa')->getCurrentOtp($secreto),
            ])
            ->assertRedirect('/panel/2fa')
            ->assertSessionHasNoErrors();

        $this->assertFalse($auditor->refresh()->hasEnabledTwoFactor());
        $this->assertNull($auditor->two_factor_secret);
    }

    public function test_el_desafio_sin_login_pendiente_vuelve_al_acceso(): void
    {
        $this->get('/panel/2fa/desafio')->assertRedirect(route('login', absolute: false));
    }
}
