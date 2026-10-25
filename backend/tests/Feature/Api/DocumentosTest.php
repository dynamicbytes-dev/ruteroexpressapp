<?php

namespace Tests\Feature\Api;

use App\Domain\Verification\Actions\RechazarDocumento;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Subida y reemplazo de documentos desde la app.
 */
class DocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function pasajeroSinDocumentos(): User
    {
        $pasajero = User::factory()->passenger()->create();

        // Todavía no subió nada: no está en la cola.
        $pasajero->forceFill(['submitted_at' => null])->save();

        return $pasajero;
    }

    public function test_el_pasajero_sube_un_documento(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', ['archivo' => UploadedFile::fake()->image('cedula.jpg')])
            ->assertOk()
            ->assertJsonPath('data.user.verification.uploaded', 1);

        $documento = $pasajero->documents()->firstOrFail();

        $this->assertSame(DocumentStatus::Pending, $documento->status);
        $this->assertSame('cedula.jpg', $documento->original_name);
        Storage::disk('local')->assertExists($documento->file_path);
        $this->assertNull($pasajero->fresh()->submitted_at, 'Falta la selfie: sigue fuera de la cola.');
    }

    public function test_al_subir_el_ultimo_documento_la_cuenta_entra_a_la_cola(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', ['archivo' => UploadedFile::fake()->image('cedula.jpg')])
            ->assertOk();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/selfie', ['archivo' => UploadedFile::fake()->image('selfie.jpg')])
            ->assertOk()
            ->assertJsonPath('message', 'Foto selfie recibido. Ya enviaste todo: tu cuenta está en espera de verificación.');

        $this->assertNotNull($pasajero->fresh()->submitted_at);
        $this->assertTrue($pasajero->fresh()->isInReviewQueue());
        $this->assertNotNull($pasajero->fresh()->avatar);
        Storage::disk('public')->assertExists($pasajero->fresh()->avatar);
    }

    public function test_selfie_debe_ser_una_imagen_valida(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/documentos/selfie', ['archivo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_reenviar_un_documento_reemplaza_el_archivo_y_lo_devuelve_a_revision(): void
    {
        $pasajero = User::factory()->passenger()->create();
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', ['archivo' => UploadedFile::fake()->image('borrosa.jpg')])
            ->assertOk();

        $documento = $pasajero->documents()->firstOrFail();
        $rutaVieja = $documento->file_path;

        // El auditor la rechaza porque no se lee.
        app(RechazarDocumento::class)->ejecutar($documento, $auditor, 'La foto está borrosa.');

        $this->assertSame('rejected', $pasajero->fresh()->status->value);
        $this->assertNull($pasajero->fresh()->submitted_at);

        // La persona vuelve a subirla.
        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', ['archivo' => UploadedFile::fake()->image('nitida.jpg')])
            ->assertOk();

        $nuevo = $pasajero->documents()->firstOrFail();

        $this->assertSame(DocumentStatus::Pending, $nuevo->status);
        $this->assertNull($nuevo->rejection_reason);
        $this->assertNull($nuevo->reviewed_at);
        $this->assertNotSame($rutaVieja, $nuevo->file_path);
        $this->assertSame(1, $pasajero->documents()->count(), 'Un solo documento vigente por tipo.');
        Storage::disk('local')->assertMissing($rutaVieja);
        Storage::disk('local')->assertExists($nuevo->file_path);
    }

    public function test_el_conductor_sube_la_foto_del_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->actingAs($conductor, 'sanctum')
            ->post('/api/documentos/foto_vehiculo', [
                'archivo' => UploadedFile::fake()->image('vehiculo.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.user.verification.uploaded', 1);

        $documento = $conductor->documents()
            ->where('type', DocumentType::VehiclePhoto)
            ->firstOrFail();

        $this->assertSame(DocumentStatus::Pending, $documento->status);
        $this->assertSame('vehiculo.jpg', $documento->original_name);
        Storage::disk('local')->assertExists($documento->file_path);
    }

    public function test_no_se_puede_subir_un_documento_que_no_corresponde_al_rol(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/rcv', ['archivo' => UploadedFile::fake()->image('rcv.jpg')])
            ->assertForbidden();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/foto_vehiculo', [
                'archivo' => UploadedFile::fake()->image('vehiculo.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('user_documents', 0);
    }

    public function test_un_tipo_de_documento_inexistente_da_404(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/pasaporte', ['archivo' => UploadedFile::fake()->image('x.jpg')])
            ->assertNotFound();
    }

    public function test_los_documentos_que_vencen_piden_la_fecha_y_la_guardan(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->actingAs($conductor, 'sanctum')
            ->post('/api/documentos/licencia_conducir', ['archivo' => UploadedFile::fake()->image('licencia.jpg')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vence_el');

        $this->actingAs($conductor, 'sanctum')
            ->post('/api/documentos/licencia_conducir', [
                'archivo' => UploadedFile::fake()->image('licencia.jpg'),
                'vence_el' => now()->subMonth()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vence_el');

        $this->actingAs($conductor, 'sanctum')
            ->post('/api/documentos/licencia_conducir', [
                'archivo' => UploadedFile::fake()->image('licencia.jpg'),
                'vence_el' => now()->addYear()->toDateString(),
            ])
            ->assertOk();

        $documento = $conductor->documents()->firstOrFail();

        $this->assertSame(now()->addYear()->toDateString(), $documento->expires_at->toDateString());
    }

    public function test_el_archivo_debe_pesar_menos_de_seis_megas(): void
    {
        $pasajero = $this->pasajeroSinDocumentos();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', [
                'archivo' => UploadedFile::fake()->image('enorme.jpg')->size(7000),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_un_documento_que_ya_esta_aprobado_puede_reemplazarse_sin_tumbar_la_cuenta(): void
    {
        $auditor = User::factory()->auditor()->create();
        $conductor = User::factory()->driver()->approved()->create();

        UserDocument::factory()->for($conductor)->ofType(DocumentType::Rcv)
            ->approved($auditor)
            ->create(['file_path' => 'documentos/viejo.pdf']);

        $this->actingAs($conductor, 'sanctum')
            ->post('/api/documentos/rcv', [
                'archivo' => UploadedFile::fake()->create('rcv-nuevo.pdf', 500, 'application/pdf'),
                'vence_el' => now()->addYear()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame('approved', $conductor->fresh()->status->value, 'La cuenta aprobada sigue operando.');
        $this->assertSame(DocumentStatus::Pending, $conductor->documents()->firstOrFail()->status);

        $aviso = $auditor->notifications()->first();

        $this->assertNotNull($aviso, 'El auditor debe enterarse de la renovación.');
        $this->assertSame('documento_por_verificar', $aviso->data['type']);
        $this->assertSame('Documento por revisar', $aviso->data['title']);
        $this->assertStringContainsString('RCV', $aviso->data['message']);
        $this->assertSame($conductor->id, $aviso->data['data']['user_id']);
    }

    public function test_reemplazar_un_documento_en_la_cola_tambien_avisa_al_auditor(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->create();

        foreach ([DocumentType::NationalId, DocumentType::Selfie] as $tipo) {
            UserDocument::factory()->for($pasajero)->ofType($tipo)->create();
        }

        $this->assertTrue($pasajero->fresh()->isInReviewQueue());

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/documentos/cedula', [
                'archivo' => UploadedFile::fake()->image('cedula-mejor.jpg'),
            ])
            ->assertOk();

        $aviso = $auditor->notifications()->first();

        $this->assertNotNull($aviso);
        $this->assertSame('documento_por_verificar', $aviso->data['type']);
        $this->assertStringContainsString('Foto de la cédula', $aviso->data['message']);
        $this->assertSame($pasajero->id, $aviso->data['data']['user_id']);
    }
}
