<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use App\Notifications\DocumentoPorVencer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeja de avisos de la app: listar, marcar uno y marcar todos.
 */
class NotificacionesApiTest extends TestCase
{
    use RefreshDatabase;

    private function avisoPara(User $usuario, DocumentType $tipo = DocumentType::DriversLicense): string
    {
        $documento = UserDocument::factory()
            ->for($usuario)
            ->ofType($tipo)
            ->approved()
            ->create(['expires_at' => now()->addDays(5)->toDateString()]);

        $usuario->notify(new DocumentoPorVencer($documento, 5));

        return $usuario->notifications()->firstOrFail()->id;
    }

    public function test_la_bandeja_pide_token(): void
    {
        $this->getJson('/api/notificaciones')->assertUnauthorized();
    }

    public function test_la_persona_ve_sus_avisos_y_el_contador_de_no_leidos(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $this->avisoPara($conductor);

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/notificaciones')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'documento_por_vencer')
            ->assertJsonPath('data.0.title', 'Documento por vencer')
            ->assertJsonPath('data.0.data.days_left', 5)
            ->assertJsonPath('data.0.data.document_type', DocumentType::DriversLicense->value)
            ->assertJsonPath('data.0.read_at', null);
    }

    public function test_cada_persona_solo_ve_sus_propios_avisos(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $otro = User::factory()->driver()->approved()->create();
        $this->avisoPara($otro);

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/notificaciones')
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_marcar_un_aviso_como_leido(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $id = $this->avisoPara($conductor);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/notificaciones/{$id}/leer")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->assertNotNull($conductor->notifications()->firstOrFail()->read_at);

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/notificaciones')
            ->assertJsonPath('unread_count', 0);
    }

    public function test_no_se_puede_marcar_el_aviso_de_otra_persona(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $otro = User::factory()->driver()->approved()->create();
        $id = $this->avisoPara($otro);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/notificaciones/{$id}/leer")
            ->assertNotFound();
    }

    public function test_marcar_todos_los_avisos_como_leidos(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $this->avisoPara($conductor);
        $this->avisoPara($conductor, DocumentType::Rcv);

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/notificaciones/leer-todas')
            ->assertOk();

        $this->assertSame(0, $conductor->unreadNotifications()->count());
    }
}
