<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_contrasena_se_actualiza(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)
            ->from('/panel/profile')
            ->put('/panel/password', [
                'current_password' => 'password',
                'password' => 'nueva-clave-2026',
                'password_confirmation' => 'nueva-clave-2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/panel/profile');

        $this->assertTrue(Hash::check('nueva-clave-2026', $auditor->refresh()->password));
    }

    public function test_hace_falta_la_contrasena_actual_para_cambiarla(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)
            ->from('/panel/profile')
            ->put('/panel/password', [
                'current_password' => 'incorrecta',
                'password' => 'nueva-clave-2026',
                'password_confirmation' => 'nueva-clave-2026',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/panel/profile');
    }

    public function test_la_contrasena_nueva_debe_traer_letras_y_numeros(): void
    {
        $auditor = User::factory()->auditor()->create(['password' => 'password']);

        $this->actingAs($auditor)
            ->from('/panel/profile')
            ->put('/panel/password', [
                'current_password' => 'password',
                'password' => 'sololetras',
                'password_confirmation' => 'sololetras',
            ])
            ->assertSessionHasErrorsIn('updatePassword', 'password')
            ->assertRedirect('/panel/profile');
    }
}
