<?php

namespace Tests\Feature\Panel;

use App\Enums\SupportConversationStatus;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Módulo de soporte del panel: atender chats, responder y conservar historial.
 */
class SoportePanelTest extends TestCase
{
    use RefreshDatabase;

    private function conversacionPara(User $usuario, string $texto = 'Necesito ayuda con mi viaje.'): SupportConversation
    {
        $conversacion = SupportConversation::query()->create([
            'user_id' => $usuario->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
        ]);

        $conversacion->messages()->create([
            'sender_id' => $usuario->id,
            'sender_type' => SupportMessage::AUTOR_USUARIO,
            'body' => $texto,
        ]);

        return $conversacion;
    }

    public function test_un_pasajero_no_entra_al_modulo_de_soporte_del_panel(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)
            ->get('/panel/soporte')
            ->assertForbidden();
    }

    public function test_el_auditor_ve_las_conversaciones_abiertas_y_el_contador_del_dashboard(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conversacion = $this->conversacionPara($pasajero);

        $this->actingAs($auditor)
            ->get('/panel/soporte')
            ->assertOk()
            ->assertSee('Soporte')
            ->assertSee($pasajero->name)
            ->assertSee('Necesito ayuda con mi viaje.');

        $dashboard = $this->actingAs($auditor)->get('/panel')->assertOk();
        $queue = collect($dashboard->viewData('queues'))
            ->firstWhere('key', 'soporte');

        $this->assertNotNull($queue);
        $this->assertSame(1, $queue['count']);

        $this->actingAs($auditor)
            ->get("/panel/soporte/{$conversacion->id}")
            ->assertOk()
            ->assertSee('Necesito ayuda con mi viaje.')
            ->assertSee('Enviar respuesta');
    }

    public function test_el_auditor_responde_y_el_usuario_recibe_el_mensaje(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conversacion = $this->conversacionPara($pasajero);

        $this->actingAs($auditor)
            ->post("/panel/soporte/{$conversacion->id}/mensajes", [
                'message' => 'Estamos revisando tu caso.',
            ])
            ->assertRedirect("/panel/soporte/{$conversacion->id}")
            ->assertSessionHas('status');

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $conversacion->id,
            'sender_id' => $auditor->id,
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => 'Estamos revisando tu caso.',
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data.messages.1.body', 'Estamos revisando tu caso.')
            ->assertJsonPath('data.messages.1.from_support', true);
    }

    public function test_el_sondeo_del_panel_devuelve_los_mensajes_y_marca_los_del_usuario_como_leidos(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conversacion = $this->conversacionPara($pasajero);

        $mensaje = $conversacion->messages()->firstOrFail();
        $this->assertNull($mensaje->read_at);

        $this->actingAs($auditor)
            ->getJson("/panel/soporte/{$conversacion->id}/mensajes")
            ->assertOk()
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('messages.0.from_user', true)
            ->assertJsonPath('messages.0.body', 'Necesito ayuda con mi viaje.');

        $this->assertNotNull($mensaje->fresh()->read_at);
    }

    public function test_al_finalizar_desaparece_de_la_app_y_queda_en_el_historial_del_panel(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conversacion = $this->conversacionPara($pasajero);

        $this->actingAs($auditor)
            ->post("/panel/soporte/{$conversacion->id}/cerrar")
            ->assertRedirect("/panel/soporte/{$conversacion->id}")
            ->assertSessionHas('status');

        $conversacion->refresh();

        $this->assertSame(SupportConversationStatus::Closed, $conversacion->status);
        $this->assertNotNull($conversacion->closed_at);
        $this->assertSame($auditor->id, $conversacion->closed_by);

        // El usuario ya no ve el chat, pero sus mensajes siguen guardados.
        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSame(1, $conversacion->messages()->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'soporte',
            'description' => 'Conversación de soporte finalizada',
        ]);

        $this->actingAs($auditor)
            ->get('/panel/soporte?tab=historial')
            ->assertOk()
            ->assertSee($pasajero->name)
            ->assertSee('Necesito ayuda con mi viaje.')
            ->assertSee('Finalizada');

        $this->actingAs($auditor)
            ->get("/panel/soporte/{$conversacion->id}")
            ->assertOk()
            ->assertSee('Conversación finalizada')
            ->assertSee('Necesito ayuda con mi viaje.');
    }

    public function test_no_se_responde_ni_se_cierra_una_conversacion_ya_finalizada(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conversacion = $this->conversacionPara($pasajero);
        $conversacion->forceFill([
            'status' => SupportConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $auditor->id,
        ])->save();

        $this->actingAs($auditor)
            ->post("/panel/soporte/{$conversacion->id}/mensajes", [
                'message' => 'Mensaje tardío.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, $conversacion->messages()->count());

        $this->actingAs($auditor)
            ->post("/panel/soporte/{$conversacion->id}/cerrar")
            ->assertSessionHas('error');
    }
}
