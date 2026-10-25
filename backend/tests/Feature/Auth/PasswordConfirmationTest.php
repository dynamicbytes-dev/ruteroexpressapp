<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_confirmacion_se_muestra(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->get('/panel/confirm-password')->assertOk();
    }

    public function test_la_contrasena_se_confirma(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->post('/panel/confirm-password', ['password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_no_se_confirma_con_una_contrasena_incorrecta(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->post('/panel/confirm-password', ['password' => 'incorrecta'])
            ->assertSessionHasErrors();
    }
}
