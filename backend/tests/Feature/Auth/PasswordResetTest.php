<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Recuperación de contraseña por correo.
 *
 * La misma pantalla sirve para el auditor (que sigue al panel) y para quien usa
 * la app (que vuelve al teléfono con un mensaje claro).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_solicitud_se_muestra(): void
    {
        $this->get('/recuperar')
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('Solo se aceptan cuentas de Gmail');
    }

    public function test_las_paginas_publicas_hablan_de_la_app_y_no_del_panel(): void
    {
        // Quien abre el enlace desde el teléfono no tiene que ver el discurso
        // del panel de auditoría.
        $this->get('/recuperar')
            ->assertOk()
            ->assertSee('Tu viaje, tu ruta')
            ->assertDontSee('Panel exclusivo para auditores');

        $this->get('/contrasena-lista')
            ->assertOk()
            ->assertSee('Tu viaje, tu ruta')
            ->assertDontSee('Panel exclusivo para auditores');
    }

    public function test_se_envia_el_enlace_de_recuperacion(): void
    {
        Notification::fake();

        $auditor = User::factory()->auditor()->create();

        $this->post('/recuperar', ['email' => $auditor->email])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertSentTo($auditor, RestablecerContrasena::class);
    }

    public function test_la_respuesta_no_revela_si_el_correo_existe(): void
    {
        Notification::fake();

        $this->post('/recuperar', ['email' => 'nadie@gmail.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_la_pantalla_de_restablecimiento_se_muestra(): void
    {
        Notification::fake();

        $auditor = User::factory()->auditor()->create();

        $this->post('/recuperar', ['email' => $auditor->email]);

        Notification::assertSentTo($auditor, RestablecerContrasena::class, function ($notificacion) use ($auditor) {
            $this->get('/restablecer/'.$notificacion->token.'?email='.urlencode($auditor->email))
                ->assertOk()
                ->assertSee('Crea una contraseña nueva');

            return true;
        });
    }

    public function test_el_auditor_restablece_su_contrasena_y_vuelve_al_panel(): void
    {
        Notification::fake();

        $auditor = User::factory()->auditor()->create();

        $this->post('/recuperar', ['email' => $auditor->email]);

        Notification::assertSentTo($auditor, RestablecerContrasena::class, function ($notificacion) use ($auditor) {
            $this->post('/restablecer', [
                'token' => $notificacion->token,
                'email' => $auditor->email,
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'clave-nueva-123',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->post('/panel/login', [
            'email' => $auditor->email,
            'password' => 'clave-nueva-123',
        ])->assertRedirect('/panel');
    }

    public function test_quien_usa_la_app_cambia_la_contrasena_y_recibe_el_aviso_para_volver(): void
    {
        Notification::fake();

        $pasajero = User::factory()->passenger()->create();

        $this->post('/recuperar', ['email' => $pasajero->email]);

        Notification::assertSentTo($pasajero, RestablecerContrasena::class, function ($notificacion) use ($pasajero) {
            $this->post('/restablecer', [
                'token' => $notificacion->token,
                'email' => $pasajero->email,
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'clave-nueva-123',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('password.reset.done'));

            return true;
        });

        $this->get('/contrasena-lista')
            ->assertOk()
            ->assertSee('Contraseña actualizada')
            ->assertSee('volver a la aplicación de GAZ');
    }

    public function test_al_cambiar_la_contrasena_se_cierran_las_sesiones_de_la_app(): void
    {
        Notification::fake();

        $pasajero = User::factory()->passenger()->create();
        $pasajero->createToken('celular');

        $this->post('/recuperar', ['email' => $pasajero->email]);

        Notification::assertSentTo($pasajero, RestablecerContrasena::class, function ($notificacion) use ($pasajero) {
            $this->post('/restablecer', [
                'token' => $notificacion->token,
                'email' => $pasajero->email,
                'password' => 'clave-nueva-123',
                'password_confirmation' => 'clave-nueva-123',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_un_token_invalido_no_cambia_la_contrasena(): void
    {
        $pasajero = User::factory()->passenger()->create(['password' => 'clave-vieja-123']);

        $this->post('/restablecer', [
            'token' => 'token-inventado',
            'email' => $pasajero->email,
            'password' => 'clave-nueva-123',
            'password_confirmation' => 'clave-nueva-123',
        ])->assertSessionHasErrors('email');

        $this->post('/api/auth/login', [
            'email' => $pasajero->email,
            'password' => 'clave-vieja-123',
        ])->assertOk();
    }
}
