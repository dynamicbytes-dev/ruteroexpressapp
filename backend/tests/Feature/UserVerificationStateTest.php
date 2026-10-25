<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserVerificationStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pasajero_nace_en_espera_de_verificacion(): void
    {
        $pasajero = User::factory()->passenger()->create();

        $this->assertSame(UserStatus::Pending, $pasajero->status);
        $this->assertSame('En espera de verificación', $pasajero->status->label());
        $this->assertFalse($pasajero->canOperate());
    }

    public function test_el_pasajero_necesita_cedula_y_selfie(): void
    {
        $pasajero = User::factory()->passenger()->create();

        $this->assertEqualsCanonicalizing(
            [DocumentType::NationalId, DocumentType::Selfie],
            $pasajero->missingDocuments(),
        );

        UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create();
        $this->assertSame([DocumentType::Selfie], $pasajero->missingDocuments());

        UserDocument::factory()->for($pasajero)->ofType(DocumentType::Selfie)->create();
        $this->assertTrue($pasajero->fresh()->hasSubmittedAllDocuments());
    }

    public function test_el_conductor_requiere_seis_documentos_incluida_la_foto_del_vehiculo(): void
    {
        $conductor = User::factory()->driver()->create();

        $this->assertEqualsCanonicalizing([
            DocumentType::NationalId,
            DocumentType::Selfie,
            DocumentType::DriversLicense,
            DocumentType::VehicleRegistration,
            DocumentType::Rcv,
            DocumentType::VehiclePhoto,
        ], $conductor->missingDocuments());
    }

    public function test_solo_una_cuenta_aprobada_puede_operar(): void
    {
        $aprobado = User::factory()->driver()->approved()->create();
        $this->assertTrue($aprobado->canOperate());

        $rechazado = User::factory()->driver()->rejected()->create();
        $this->assertFalse($rechazado->canOperate());

        $suspendido = User::factory()->driver()->create(['status' => UserStatus::Suspended]);
        $this->assertFalse($suspendido->canOperate());
    }

    public function test_un_bloqueo_temporal_impide_operar_aunque_la_cuenta_este_aprobada(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        // El bloqueo lo fija el sistema, no la entrada del usuario,
        // por eso no es asignable en masa.
        $conductor->forceFill(['blocked_until' => now()->addHour()])->save();

        $this->assertFalse($conductor->canOperate());

        $conductor->forceFill(['blocked_until' => now()->subMinute()])->save();
        $this->assertTrue($conductor->fresh()->canOperate());
    }

    public function test_el_auditor_no_esta_en_el_registro_publico(): void
    {
        $this->assertSame([], UserRole::Auditor->requiredDocuments());
        $this->assertNotContains(UserRole::Auditor, UserRole::selfRegistrable());
    }

    public function test_el_nombre_completo_se_arma_con_nombre_y_apellido(): void
    {
        $usuario = User::factory()->create([
            'first_name' => 'Maria',
            'last_name' => 'Perez',
            'national_id' => '12345678',
        ]);

        $this->assertSame('Maria Perez', $usuario->name);
        $this->assertSame('Maria Perez · V-12345678', $usuario->nameWithId);
    }
}
