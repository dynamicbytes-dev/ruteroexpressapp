<?php

namespace Tests\Feature\Panel;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VerificationReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Verificación de usuarios desde el panel del auditor: cola, ficha,
 * aprobación y rechazo de documentos y de la cuenta.
 */
class VerificacionTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    /** Pasajero con los dos documentos aprobados y listo para aprobar. */
    private function pasajeroListo(User $auditor): User
    {
        $pasajero = User::factory()->passenger()->create();

        foreach ([DocumentType::NationalId, DocumentType::Selfie] as $tipo) {
            UserDocument::factory()->for($pasajero)->ofType($tipo)->approved($auditor)->create();
        }

        return $pasajero;
    }

    public function test_un_no_auditor_no_entra_a_la_cola_de_usuarios(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/usuarios')->assertForbidden();
        $this->actingAs($pasajero)->get('/panel/usuarios/'.$pasajero->id)->assertForbidden();
    }

    public function test_la_cola_muestra_solo_a_quien_ya_envio_sus_documentos(): void
    {
        $auditor = $this->auditor();

        $completo = User::factory()->passenger()->create(['first_name' => 'Ana', 'submitted_at' => now()]);
        $aMedias = User::factory()->passenger()->create(['first_name' => 'Bruno']);
        $aMedias->forceFill(['submitted_at' => null])->save();

        $this->actingAs($auditor)
            ->get('/panel/usuarios')
            ->assertOk()
            ->assertSee('Ana')
            ->assertDontSee('Bruno')
            ->assertSee('Por verificar');
    }

    public function test_el_auditor_ve_los_documentos_que_una_cuenta_aprobada_renovo(): void
    {
        $auditor = $this->auditor();

        $aprobado = User::factory()->passenger()->approved()->create(['first_name' => 'Renata']);
        UserDocument::factory()->for($aprobado)->ofType(DocumentType::NationalId)
            ->approved($auditor)->create();
        UserDocument::factory()->for($aprobado)->ofType(DocumentType::Selfie)->create();

        $enCola = User::factory()->passenger()->create(['first_name' => 'Colado']);
        UserDocument::factory()->for($enCola)->ofType(DocumentType::NationalId)->create();
        UserDocument::factory()->for($enCola)->ofType(DocumentType::Selfie)->create();

        $this->actingAs($auditor)
            ->get('/panel/usuarios?tab=documentos')
            ->assertOk()
            ->assertSee('Renata')
            ->assertDontSee('Colado')
            ->assertSee('1 por revisar');

        $this->actingAs($auditor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Documentos por revisar');
    }

    public function test_la_ficha_muestra_los_documentos_y_los_que_faltan(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();

        $this->actingAs($auditor)
            ->get('/panel/usuarios/'.$pasajero->id)
            ->assertOk()
            ->assertSee('Foto de la cédula')
            ->assertSee('Foto selfie')
            ->assertSee('La persona todavía no lo envió.')
            ->assertSee('En espera de verificación')
            ->assertSee('Corregir cédula');
    }

    public function test_el_auditor_aprueba_un_documento_y_queda_registrado(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        $documento = UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.documentos.aprobar', $documento))
            ->assertRedirect(route('panel.usuarios.show', $pasajero));

        $documento->refresh();

        $this->assertSame(DocumentStatus::Approved, $documento->status);
        $this->assertSame($auditor->id, $documento->reviewed_by);
        $this->assertNotNull($documento->reviewed_at);

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $pasajero->id,
            'auditor_id' => $auditor->id,
            'scope' => VerificationReview::SCOPE_DOCUMENT,
            'document_id' => $documento->id,
            'decision' => VerificationReview::DECISION_APPROVED,
        ]);
    }

    public function test_rechazar_un_documento_exige_el_motivo(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();
        $documento = UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.documentos.rechazar', $documento), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(DocumentStatus::Pending, $documento->fresh()->status);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.documentos.rechazar', $documento), ['reason' => 'corto'])
            ->assertSessionHasErrors('reason');
    }

    public function test_rechazar_un_documento_deja_la_cuenta_rechazada_con_el_motivo(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();
        $documento = UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.documentos.rechazar', $documento), [
                'reason' => 'La foto está borrosa y no se lee el número.',
            ])
            ->assertRedirect(route('panel.usuarios.show', $pasajero));

        $documento->refresh();

        $this->assertSame(DocumentStatus::Rejected, $documento->status);
        $this->assertSame('La foto está borrosa y no se lee el número.', $documento->rejection_reason);

        $pasajero->refresh();

        $this->assertSame(UserStatus::Rejected, $pasajero->status);
        $this->assertStringContainsString('La foto está borrosa', $pasajero->status_reason);
        $this->assertNull($pasajero->submitted_at, 'Sale de la cola hasta que corrija.');

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $pasajero->id,
            'scope' => VerificationReview::SCOPE_DOCUMENT,
            'decision' => VerificationReview::DECISION_REJECTED,
        ]);
    }

    public function test_no_se_puede_aprobar_la_cuenta_con_documentos_sin_aprobar(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.aprobar', $pasajero))
            ->assertSessionHasErrors('account');

        $this->assertSame(UserStatus::Pending, $pasajero->fresh()->status);
        $this->assertFalse($pasajero->fresh()->canOperate());
    }

    public function test_un_conductor_sin_foto_del_vehiculo_no_se_puede_aprobar(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->create();

        foreach ($conductor->role->requiredDocuments() as $tipo) {
            if ($tipo === DocumentType::VehiclePhoto) {
                continue;
            }

            UserDocument::factory()
                ->for($conductor)
                ->ofType($tipo)
                ->approved($auditor)
                ->create([
                    'expires_at' => $tipo->expires() ? now()->addYear()->toDateString() : null,
                ]);
        }

        $this->assertContains(DocumentType::VehiclePhoto, $conductor->missingDocuments());
        $this->assertFalse($conductor->fresh()->canBeApproved());

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $conductor))
            ->post(route('panel.usuarios.aprobar', $conductor))
            ->assertSessionHasErrors('account');

        $this->assertSame(UserStatus::Pending, $conductor->fresh()->status);
    }

    public function test_la_cuenta_se_aprueba_cuando_todos_los_documentos_estan_aprobados(): void
    {
        $auditor = $this->auditor();
        $pasajero = $this->pasajeroListo($auditor);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.aprobar', $pasajero))
            ->assertRedirect(route('panel.usuarios.show', $pasajero));

        $pasajero->refresh();

        $this->assertSame(UserStatus::Approved, $pasajero->status);
        $this->assertNotNull($pasajero->approved_at);
        $this->assertNull($pasajero->submitted_at);
        $this->assertTrue($pasajero->canOperate(), 'Ya puede pedir viajes.');

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $pasajero->id,
            'scope' => VerificationReview::SCOPE_ACCOUNT,
            'decision' => VerificationReview::DECISION_APPROVED,
        ]);
    }

    public function test_rechazar_la_cuenta_exige_el_motivo_y_la_deja_rechazada(): void
    {
        $auditor = $this->auditor();
        $pasajero = $this->pasajeroListo($auditor);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.rechazar', $pasajero), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(UserStatus::Pending, $pasajero->fresh()->status);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.rechazar', $pasajero), [
                'reason' => 'Los datos del vehículo no coinciden con el carnet de circulación.',
            ])
            ->assertRedirect(route('panel.usuarios.show', $pasajero));

        $pasajero->refresh();

        $this->assertSame(UserStatus::Rejected, $pasajero->status);
        $this->assertSame('Los datos del vehículo no coinciden con el carnet de circulación.', $pasajero->status_reason);
    }

    public function test_el_documento_solo_se_entrega_con_enlace_firmado(): void
    {
        Storage::fake('local');

        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        Storage::disk('local')->put('documentos/cedula.jpg', 'contenido-de-la-foto');

        $documento = UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create([
            'file_path' => 'documentos/cedula.jpg',
            'mime_type' => 'image/jpeg',
            'original_name' => 'cedula.jpg',
        ]);

        // Sin firma no se entrega (la ruta lleva el middleware signed).
        $this->actingAs($auditor)
            ->get(route('panel.documents.show', $documento))
            ->assertForbidden();

        $respuesta = $this->actingAs($auditor)->get($documento->temporaryUrl());

        $respuesta->assertOk();
        $this->assertSame('contenido-de-la-foto', $respuesta->streamedContent());
    }

    public function test_el_auditor_no_puede_revisar_su_propia_cuenta(): void
    {
        $auditor = $this->auditor();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.aprobar', $auditor))
            ->assertForbidden();
    }

    public function test_el_flujo_completo_desde_la_cola_hasta_la_cuenta_aprobada(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create();

        $cedula = UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();
        $selfie = UserDocument::factory()->for($pasajero)->ofType(DocumentType::Selfie)->create();

        $this->actingAs($auditor)->get('/panel/usuarios')->assertSee($pasajero->name);

        foreach ([$cedula, $selfie] as $documento) {
            $this->actingAs($auditor)
                ->from(route('panel.usuarios.show', $pasajero))
                ->post(route('panel.documentos.aprobar', $documento))
                ->assertRedirect();
        }

        // Con todo aprobado, la ficha habilita la aprobación de la cuenta.
        $this->actingAs($auditor)
            ->get('/panel/usuarios/'.$pasajero->id)
            ->assertSee('Todos los documentos están aprobados');

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.aprobar', $pasajero))
            ->assertRedirect();

        $this->assertTrue($pasajero->fresh()->canOperate());
        $this->assertSame(3, $pasajero->verificationReviews()->count(), 'Dos documentos y la cuenta.');
    }

    public function test_el_auditor_corrige_la_cedula_con_motivo_y_queda_en_auditoria(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create(['national_id' => '12345678']);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.cedula', $pasajero), [
                'national_id' => '87654321',
                'reason' => 'La persona digitó mal su cédula al registrarse.',
            ])
            ->assertRedirect(route('panel.usuarios.show', $pasajero))
            ->assertSessionHas('status');

        $this->assertSame('87654321', $pasajero->fresh()->national_id);

        $actividad = Activity::query()->latest('id')->firstOrFail();

        $this->assertSame('usuarios', $actividad->log_name);
        $this->assertSame('Corrigió la cédula', $actividad->description);
        $this->assertSame($auditor->id, $actividad->causer_id);
        $this->assertSame($pasajero->id, $actividad->subject_id);
        $this->assertSame('12345678', $actividad->properties['old']['national_id']);
        $this->assertSame('87654321', $actividad->properties['attributes']['national_id']);
        $this->assertSame(
            'La persona digitó mal su cédula al registrarse.',
            $actividad->properties['reason'],
        );
    }

    public function test_corregir_la_cedula_exige_motivo_y_no_permite_duplicados(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->create(['national_id' => '12345678']);
        User::factory()->passenger()->create(['national_id' => '87654321']);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.cedula', $pasajero), [
                'national_id' => '11111111',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.cedula', $pasajero), [
                'national_id' => '87654321',
                'reason' => 'Corrección solicitada por la persona.',
            ])
            ->assertSessionHasErrors('national_id');

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.cedula', $pasajero), [
                'national_id' => '123',
                'reason' => 'Corrección solicitada por la persona.',
            ])
            ->assertSessionHasErrors('national_id');

        $this->assertSame('12345678', $pasajero->fresh()->national_id);
        $this->assertFalse(
            Activity::query()->where('description', 'Corrigió la cédula')->exists(),
        );
    }

    public function test_solo_un_auditor_puede_corregir_la_cedula(): void
    {
        $pasajero = User::factory()->passenger()->create(['national_id' => '12345678']);
        $otro = User::factory()->passenger()->create(['national_id' => '87654321']);

        $this->actingAs($otro)
            ->post(route('panel.usuarios.cedula', $pasajero), [
                'national_id' => '11111111',
                'reason' => 'Intento de cambiar una cédula ajena.',
            ])
            ->assertForbidden();

        $this->assertSame('12345678', $pasajero->fresh()->national_id);
    }
}
