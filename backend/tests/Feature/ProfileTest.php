<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_cuenta_se_muestra(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->get('/panel/profile')->assertOk();
    }

    public function test_los_datos_de_la_cuenta_se_actualizan(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->patch('/panel/profile', [
                'first_name' => 'Ana',
                'last_name' => 'Rivas',
                'email' => 'ana.rivas@gmail.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/panel/profile');

        $auditor->refresh();

        $this->assertSame('Ana Rivas', $auditor->name);
        $this->assertSame('ana.rivas@gmail.com', $auditor->email);
    }

    public function test_el_correo_no_puede_repetirse(): void
    {
        $auditor = User::factory()->auditor()->create();
        $otro = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->patch('/panel/profile', [
                'first_name' => 'Ana',
                'last_name' => 'Rivas',
                'email' => $otro->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_el_estado_de_verificacion_no_cambia_si_el_correo_es_el_mismo(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->patch('/panel/profile', [
                'first_name' => $auditor->first_name,
                'last_name' => $auditor->last_name,
                'email' => $auditor->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($auditor->refresh()->email_verified_at);
    }

    public function test_la_cuenta_se_elimina_con_la_contrasena_correcta(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)
            ->delete('/panel/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        // La eliminación es lógica: el histórico de auditoría se conserva.
        $this->assertSoftDeleted($auditor);
    }

    public function test_hace_falta_la_contrasena_correcta_para_eliminar_la_cuenta(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)
            ->from('/panel/profile')
            ->delete('/panel/profile', ['password' => 'incorrecta'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/panel/profile');

        $this->assertNotSoftDeleted($auditor);
    }
}
