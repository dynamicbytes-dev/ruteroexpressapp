<?php

namespace Tests\Feature\Api;

use App\Enums\SupportConversationStatus;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chat de soporte de la app: conversación abierta, mensajes y cierre.
 */
class SoporteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_chat_pide_token(): void
    {
        $this->getJson('/api/soporte')->assertUnauthorized();
        $this->postJson('/api/soporte/mensajes', ['message' => 'Hola'])->assertUnauthorized();
    }

    public function test_sin_conversacion_abierta_devuelve_null(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_enviar_el_primer_mensaje_crea_la_conversacion(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $respuesta = $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', [
                'message' => '  Necesito ayuda con mi recarga.  ',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.messages.0.body', 'Necesito ayuda con mi recarga.')
            ->assertJsonPath('data.messages.0.from_support', false);

        $this->assertDatabaseHas('support_conversations', [
            'id' => $respuesta->json('data.id'),
            'user_id' => $pasajero->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('support_messages', [
            'support_conversation_id' => $respuesta->json('data.id'),
            'sender_id' => $pasajero->id,
            'sender_type' => SupportMessage::AUTOR_USUARIO,
            'body' => 'Necesito ayuda con mi recarga.',
        ]);
    }

    public function test_el_primer_mensaje_avisa_al_auditor_y_no_repite_mientras_siga_sin_leer(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();

        $respuesta = $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', [
                'message' => 'Necesito ayuda con mi recarga.',
            ])
            ->assertOk();

        $conversacion = SupportConversation::query()->findOrFail($respuesta->json('data.id'));
        $aviso = $auditor->notifications()->first();

        $this->assertNotNull($aviso);
        $this->assertSame('soporte_mensaje', $aviso->data['type']);
        $this->assertSame('Nuevo mensaje de soporte', $aviso->data['title']);
        $this->assertStringContainsString('Necesito ayuda con mi recarga.', $aviso->data['message']);
        $this->assertSame($conversacion->id, $aviso->data['data']['conversation_id']);

        // Mientras el auditor no abra el hilo, los mensajes nuevos no repiten
        // la campana: ya hay un aviso pendiente por esta conversación.
        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => '¿Alguien me lee?'])
            ->assertOk();

        $this->assertSame(1, $auditor->notifications()->count());

        // Abrir la conversación en el panel marca los mensajes como leídos.
        $this->actingAs($auditor)
            ->get(route('panel.soporte.show', $conversacion))
            ->assertOk();

        $this->assertSame(
            0,
            $conversacion->messages()
                ->where('sender_type', SupportMessage::AUTOR_USUARIO)
                ->whereNull('read_at')
                ->count(),
        );

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => 'Sigo esperando respuesta.'])
            ->assertOk();

        $this->assertSame(2, $auditor->notifications()->count());
    }

    public function test_un_conductor_tambien_puede_usar_el_chat(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => 'No puedo conectarme.'])
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'No puedo conectarme.');
    }

    public function test_el_auditor_no_usa_el_chat_de_la_app(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/soporte')
            ->assertForbidden();

        $this->actingAs($auditor, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => 'Hola'])
            ->assertForbidden();
    }

    public function test_cada_persona_solo_ve_su_propia_conversacion(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();

        $conversacion = SupportConversation::query()->create([
            'user_id' => $otro->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
        ]);
        $conversacion->messages()->create([
            'sender_id' => $otro->id,
            'sender_type' => SupportMessage::AUTOR_USUARIO,
            'body' => 'Mensaje privado de otra persona.',
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($otro, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Mensaje privado de otra persona.');
    }

    public function test_la_persona_ve_y_marca_como_leida_la_respuesta_del_equipo(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $auditor = User::factory()->auditor()->create();

        $conversacion = SupportConversation::query()->create([
            'user_id' => $pasajero->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
        ]);
        $mensaje = $conversacion->messages()->create([
            'sender_id' => $auditor->id,
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => 'Estamos revisando tu caso.',
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'Estamos revisando tu caso.')
            ->assertJsonPath('data.messages.0.from_support', true)
            ->assertJsonPath('data.messages.0.author_label', $auditor->name)
            ->assertJsonPath('data.auditor_name', $auditor->name);

        $this->assertNotNull($mensaje->fresh()->read_at);
    }

    public function test_cada_respuesta_muestra_el_auditor_que_la_escribio(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $ana = User::factory()->auditor()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
        ]);
        $luis = User::factory()->auditor()->create([
            'first_name' => 'Luis',
            'last_name' => 'Gómez',
        ]);

        $conversacion = SupportConversation::query()->create([
            'user_id' => $pasajero->id,
            'status' => SupportConversationStatus::Open,
            'last_message_at' => now(),
        ]);
        $conversacion->messages()->create([
            'sender_id' => $ana->id,
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => 'Te atiende Ana.',
        ]);
        $conversacion->messages()->create([
            'sender_id' => $luis->id,
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => 'Y yo continúo el caso.',
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data.auditor_name', 'Ana Pérez')
            ->assertJsonPath('data.messages.0.author_label', 'Ana Pérez')
            ->assertJsonPath('data.messages.1.author_label', 'Luis Gómez');
    }

    public function test_al_finalizar_el_chat_la_persona_puede_iniciar_otro(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $cerrada = SupportConversation::query()->create([
            'user_id' => $pasajero->id,
            'status' => SupportConversationStatus::Closed,
            'closed_at' => now()->subHour(),
            'last_message_at' => now()->subHour(),
        ]);
        $cerrada->messages()->create([
            'sender_id' => $pasajero->id,
            'sender_type' => SupportMessage::AUTOR_USUARIO,
            'body' => 'Consulta anterior.',
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/soporte')
            ->assertOk()
            ->assertJsonPath('data', null);

        $respuesta = $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => 'Consulta nueva.'])
            ->assertOk();

        $this->assertNotSame($cerrada->id, $respuesta->json('data.id'));
        $this->assertSame(2, SupportConversation::query()->count());
        $this->assertSame(
            SupportConversationStatus::Closed,
            $cerrada->fresh()->status,
        );
        $this->assertSame(1, $cerrada->messages()->count());
    }

    public function test_el_mensaje_no_puede_estar_vacio_ni_pasar_de_2000_caracteres(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/soporte/mensajes', [
                'message' => str_repeat('a', 2001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, SupportConversation::query()->count());
    }
}
