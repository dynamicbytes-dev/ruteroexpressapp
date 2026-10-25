<?php

namespace Tests\Feature\Panel;

use App\Enums\SupportConversationStatus;
use App\Models\Recharge;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\VerificationReview;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El auditor principal puede comparar la carga de trabajo de los auditores
 * secundarios. Los secundarios no ven esta vista.
 */
class AuditorRendimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_el_auditor_principal_entra_al_rendimiento(): void
    {
        $secundario = User::factory()->auditor()->create();

        $this->actingAs($secundario)
            ->get('/panel/auditores/rendimiento')
            ->assertForbidden();
    }

    public function test_el_principal_ve_solo_a_los_auditores_secundarios(): void
    {
        $principal = User::factory()->auditor()->owner()->create([
            'first_name' => 'Ana',
            'last_name' => 'Principal',
        ]);
        $secundario = User::factory()->auditor()->create([
            'first_name' => 'Luis',
            'last_name' => 'Secundario',
        ]);

        $respuesta = $this->actingAs($principal)
            ->get('/panel/auditores/rendimiento')
            ->assertOk()
            ->assertSee('Rendimiento de auditores')
            ->assertSee('Luis Secundario');

        $filas = $respuesta->viewData('filas');

        $this->assertCount(1, $filas);
        $this->assertSame($secundario->id, $filas->first()['auditor']->id);
    }

    public function test_calcula_decisiones_soporte_y_finanzas_por_auditor(): void
    {
        $principal = User::factory()->auditor()->owner()->create();
        $secundario = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();

        VerificationReview::factory()->create([
            'auditor_id' => $secundario->id,
            'user_id' => $pasajero->id,
            'decision' => VerificationReview::DECISION_APPROVED,
        ]);
        VerificationReview::factory()->create([
            'auditor_id' => $secundario->id,
            'user_id' => $pasajero->id,
            'decision' => VerificationReview::DECISION_REJECTED,
        ]);

        $conversacion = SupportConversation::query()->create([
            'user_id' => $pasajero->id,
            'status' => SupportConversationStatus::Closed,
            'last_message_at' => now(),
            'closed_at' => now(),
            'closed_by' => $secundario->id,
        ]);
        $conversacion->messages()->create([
            'sender_id' => $secundario->id,
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => 'Tu caso quedó resuelto.',
            'read_at' => now(),
        ]);

        Recharge::factory()->approved(40, $secundario)->create();
        Withdrawal::factory()->paid($secundario)->create();

        $respuesta = $this->actingAs($principal)
            ->get('/panel/auditores/rendimiento')
            ->assertOk();

        $fila = $respuesta->viewData('filas')
            ->firstWhere(fn (array $fila): bool => $fila['auditor']->id === $secundario->id);

        $this->assertNotNull($fila);
        $this->assertSame(2, $fila['decisiones']);
        $this->assertSame(1, $fila['aprobaciones']);
        $this->assertSame(1, $fila['rechazos']);
        $this->assertSame(50, $fila['tasa_aprobacion']);
        $this->assertSame(1, $fila['soporte_mensajes']);
        $this->assertSame(1, $fila['soporte_cierres']);
        $this->assertSame(1, $fila['recargas']);
        $this->assertSame(1, $fila['retiros']);
        $this->assertSame(6, $fila['gestiones']);
    }

    public function test_el_periodo_deja_fuera_las_gestiones_viejas(): void
    {
        $principal = User::factory()->auditor()->owner()->create();
        $secundario = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();

        VerificationReview::factory()->create([
            'auditor_id' => $secundario->id,
            'user_id' => $pasajero->id,
            'created_at' => now()->subDays(60),
        ]);

        $hace30 = $this->actingAs($principal)
            ->get('/panel/auditores/rendimiento?dias=30')
            ->assertOk();
        $hace90 = $this->actingAs($principal)
            ->get('/panel/auditores/rendimiento?dias=90')
            ->assertOk();

        $fila30 = $hace30->viewData('filas')
            ->firstWhere(fn (array $fila): bool => $fila['auditor']->id === $secundario->id);
        $fila90 = $hace90->viewData('filas')
            ->firstWhere(fn (array $fila): bool => $fila['auditor']->id === $secundario->id);

        $this->assertSame(0, $fila30['decisiones']);
        $this->assertSame(1, $fila90['decisiones']);
    }
}
