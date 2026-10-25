<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Notifications\AvisoAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bandeja de avisos operativos del auditor: solo panel, sin correo.
 */
class NotificacionesPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_auditor_ve_la_campana_y_marca_sus_avisos(): void
    {
        $auditor = User::factory()->auditor()->create();

        $auditor->notify(new AvisoAuditor(
            'recarga_pendiente',
            'Nueva recarga por revisar',
            'Una persona reportó una recarga.',
            route('panel.recargas.index'),
            ['recharge_id' => 10],
        ));

        $auditor->notify(new AvisoAuditor(
            'documentos_vencimiento',
            'Documentos por vencer',
            'Hay documentos que requieren atención.',
            route('panel.conductores.index'),
        ));

        $this->actingAs($auditor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Notificaciones');

        $this->actingAs($auditor)
            ->get('/panel/notificaciones')
            ->assertOk()
            ->assertSee('Nueva recarga por revisar')
            ->assertSee('No leídas');

        $this->actingAs($auditor)
            ->getJson('/panel/notificaciones/contador')
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $aviso = $auditor->notifications()
            ->get()
            ->firstWhere(fn ($notificacion): bool => $notificacion->data['type'] === 'recarga_pendiente');

        $this->assertNotNull($aviso);

        $this->actingAs($auditor)
            ->post("/panel/notificaciones/{$aviso->id}/leer")
            ->assertRedirect(route('panel.recargas.index'));

        $this->assertNotNull($aviso->fresh()->read_at);

        $this->actingAs($auditor)
            ->post('/panel/notificaciones/leer-todas')
            ->assertRedirect();

        $this->assertSame(0, $auditor->unreadNotifications()->count());
    }

    public function test_una_recarga_pendiente_avisa_al_auditor_en_el_panel(): void
    {
        Storage::fake('local');

        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/recargas', [
                'amount_bs' => 4000,
                'bank' => 'Banesco',
                'reference' => 'REF-123',
                'transferred_at' => today()->toDateString(),
                'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
            ])
            ->assertCreated();

        $aviso = $auditor->notifications()->first();

        $this->assertNotNull($aviso);
        $this->assertSame('recarga_pendiente', $aviso->data['type']);
        $this->assertSame('Nueva recarga por revisar', $aviso->data['title']);
        $this->assertStringContainsString('Banesco', $aviso->data['message']);

        $this->actingAs($auditor)
            ->get('/panel/notificaciones')
            ->assertOk()
            ->assertSee('Nueva recarga por revisar');
    }

    public function test_un_pasajero_no_entra_a_la_bandeja_del_auditor(): void
    {
        $pasajero = User::factory()->passenger()->create();

        $this->actingAs($pasajero)
            ->get('/panel/notificaciones')
            ->assertForbidden();
    }
}
