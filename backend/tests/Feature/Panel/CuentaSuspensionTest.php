<?php

namespace Tests\Feature\Panel;

use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\VerificationReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suspensión manual y reactivación de cuentas desde el panel del auditor.
 */
class CuentaSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_el_auditor_suspende_una_cuenta_con_motivo_y_queda_registrado(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.suspender', $pasajero), [
                'reason' => 'Se detectaron cobros duplicados en su billetera.',
            ])
            ->assertRedirect(route('panel.usuarios.show', $pasajero))
            ->assertSessionHas('status');

        $pasajero->refresh();

        $this->assertSame(UserStatus::Suspended, $pasajero->status);
        $this->assertSame('Se detectaron cobros duplicados en su billetera.', $pasajero->status_reason);
        $this->assertFalse($pasajero->canOperate());

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $pasajero->id,
            'auditor_id' => $auditor->id,
            'scope' => VerificationReview::SCOPE_ACCOUNT,
            'decision' => VerificationReview::DECISION_SUSPENDED,
            'reason' => 'Se detectaron cobros duplicados en su billetera.',
        ]);
    }

    public function test_no_se_puede_suspender_sin_motivo_o_con_un_motivo_muy_corto(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.suspender', $pasajero), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.suspender', $pasajero), ['reason' => 'corto'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(UserStatus::Approved, $pasajero->fresh()->status);
    }

    public function test_solo_se_suspende_una_cuenta_aprobada(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.suspender', $pasajero), [
                'reason' => 'La cuenta todavía no estaba aprobada.',
            ])
            ->assertSessionHasErrors('account');

        $this->assertSame(UserStatus::Pending, $pasajero->fresh()->status);
    }

    public function test_suspender_a_un_conductor_lo_desconecta(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create([
            'last_location_at' => now(),
        ]);

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.suspender', $conductor), [
                'reason' => 'El vehículo no coincide con el carnet de circulación.',
            ])
            ->assertSessionHas('status');

        $this->assertFalse($perfil->fresh()->is_online);
        $this->assertSame(UserStatus::Suspended, $conductor->fresh()->status);
    }

    public function test_el_auditor_reactiva_una_cuenta_suspendida(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        $pasajero->forceFill([
            'status' => UserStatus::Suspended,
            'status_reason' => 'Revisión interna.',
        ])->save();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.reactivar', $pasajero))
            ->assertRedirect(route('panel.usuarios.show', $pasajero))
            ->assertSessionHas('status');

        $pasajero->refresh();

        $this->assertSame(UserStatus::Approved, $pasajero->status);
        $this->assertNull($pasajero->status_reason);
        $this->assertTrue($pasajero->canOperate());

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $pasajero->id,
            'scope' => VerificationReview::SCOPE_ACCOUNT,
            'decision' => VerificationReview::DECISION_REACTIVATED,
        ]);
    }

    public function test_no_se_reactiva_una_cuenta_que_no_esta_suspendida(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.reactivar', $pasajero))
            ->assertSessionHasErrors('account');

        $this->assertSame(UserStatus::Approved, $pasajero->fresh()->status);
    }

    public function test_la_ficha_muestra_las_acciones_segun_el_estado(): void
    {
        $auditor = $this->auditor();
        $aprobado = User::factory()->passenger()->approved()->create();
        $suspendido = User::factory()->passenger()->approved()->create();
        $suspendido->forceFill([
            'status' => UserStatus::Suspended,
            'status_reason' => 'Documentos vencidos.',
        ])->save();

        $this->actingAs($auditor)
            ->get(route('panel.usuarios.show', $aprobado))
            ->assertOk()
            ->assertSee('Suspender la cuenta');

        $this->actingAs($auditor)
            ->get(route('panel.usuarios.show', $suspendido))
            ->assertOk()
            ->assertSee('Reactivar la cuenta')
            ->assertSee('Documentos vencidos.');
    }
}
