<?php

namespace Tests\Feature;

use App\Domain\Verification\Actions\AprobarDocumento;
use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Models\UserDocument;
use App\Notifications\DocumentoPorVencer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Avisos de documentos por vencer (30/15/5), paso a revisión al vencer y
 * bloqueo de aprobación de documentos vencidos.
 */
class DocumentExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array{0: User, 1: DriverProfile, 2: UserDocument} */
    private function conductorConDocumento(Carbon $vence, DocumentType $tipo = DocumentType::DriversLicense): array
    {
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create(['last_location_at' => now()]);
        $documento = UserDocument::factory()
            ->for($conductor)
            ->ofType($tipo)
            ->approved()
            ->create(['expires_at' => $vence->toDateString()]);

        return [$conductor->fresh(), $perfil->fresh(), $documento->fresh()];
    }

    public function test_avisa_quince_dias_antes_y_no_repite_el_mismo_umbral(): void
    {
        Notification::fake();

        [$conductor, , $documento] = $this->conductorConDocumento(now()->addDays(10));

        $this->assertSame(10, $documento->daysUntilExpiration());

        $this->artisan('documentos:avisar-vencimientos')->assertSuccessful();

        Notification::assertSentTo(
            $conductor,
            DocumentoPorVencer::class,
            fn (DocumentoPorVencer $aviso): bool => $aviso->diasRestantes === 10
                && $aviso->documento->id === $documento->id,
        );

        $this->assertSame(15, $documento->fresh()->expiry_notice_days);

        $this->artisan('documentos:avisar-vencimientos')->assertSuccessful();

        Notification::assertSentToTimes($conductor, DocumentoPorVencer::class, 1);
    }

    public function test_avisa_cinco_dias_antes_despues_del_aviso_de_quince(): void
    {
        Notification::fake();

        [$conductor, , $documento] = $this->conductorConDocumento(now()->addDays(10));

        $this->artisan('documentos:avisar-vencimientos')->assertSuccessful();

        Carbon::setTestNow(now()->addDays(6));
        $this->artisan('documentos:avisar-vencimientos')->assertSuccessful();

        Notification::assertSentToTimes($conductor, DocumentoPorVencer::class, 2);
        $this->assertSame(5, $documento->fresh()->expiry_notice_days);
    }

    public function test_un_documento_vencido_desconecta_al_conductor_y_pasa_la_cuenta_a_revision(): void
    {
        Notification::fake();

        [$conductor, $perfil, $documento] = $this->conductorConDocumento(now()->subDay());

        $this->artisan('documentos:avisar-vencimientos')->assertSuccessful();

        $conductor->refresh();
        $perfil->refresh();

        $this->assertSame(UserStatus::Pending, $conductor->status);
        $this->assertStringContainsString('Documento vencido', (string) $conductor->status_reason);
        $this->assertNotNull($conductor->submitted_at);
        $this->assertFalse($perfil->is_online);
        $this->assertSame(0, $documento->fresh()->expiry_notice_days);

        Notification::assertSentTo(
            $conductor,
            DocumentoPorVencer::class,
            fn (DocumentoPorVencer $aviso): bool => $aviso->diasRestantes < 0,
        );

        Notification::assertSentToTimes($conductor, DocumentoPorVencer::class, 1);
    }

    public function test_un_documento_vencido_no_se_puede_aprobar(): void
    {
        [$conductor, , $documento] = $this->conductorConDocumento(now()->subDay());
        $auditor = User::factory()->auditor()->create();

        $this->expectException(ValidationException::class);

        app(AprobarDocumento::class)->ejecutar($documento, $auditor);
    }

    public function test_la_cuenta_con_un_documento_vencido_no_se_puede_aprobar(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $auditor = User::factory()->auditor()->create();

        foreach ($conductor->role->requiredDocuments() as $tipo) {
            UserDocument::factory()
                ->for($conductor)
                ->ofType($tipo)
                ->approved($auditor)
                ->create([
                    'expires_at' => $tipo->expires() ? now()->addYear()->toDateString() : null,
                ]);
        }

        $this->assertTrue($conductor->fresh()->canBeApproved());

        $conductor->documents()->where('type', DocumentType::Rcv->value)->update([
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($conductor->fresh()->canBeApproved());
    }

    public function test_los_dias_hasta_el_vencimiento_son_positivos_en_el_futuro(): void
    {
        $documento = UserDocument::factory()->make([
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);

        $this->assertSame(10, $documento->daysUntilExpiration());
        $this->assertFalse($documento->isExpired());
        $this->assertTrue($documento->isExpiringSoon());
    }

    public function test_un_documento_vence_al_terminar_el_dia_indicado(): void
    {
        $documento = UserDocument::factory()->make([
            'expires_at' => now()->toDateString(),
        ]);

        $this->assertSame(0, $documento->daysUntilExpiration());
        $this->assertFalse($documento->isExpired());

        Carbon::setTestNow(now()->addDay()->startOfDay());

        $this->assertTrue($documento->isExpired());
    }
}
