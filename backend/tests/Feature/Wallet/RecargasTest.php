<?php

namespace Tests\Feature\Wallet;

use App\Domain\Wallet\Actions\AprobarRecarga;
use App\Domain\Wallet\Actions\RechazarRecarga;
use App\Enums\RechargeStatus;
use App\Enums\WalletTransactionType;
use App\Models\ExchangeRate;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Recargas con comprobante: solicitud desde la app y aprobación del auditor.
 */
class RecargasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function pasajeroAprobado(): User
    {
        return User::factory()->passenger()->approved()->create();
    }

    private function solicitar(User $usuario, array $extra = [], ?UploadedFile $archivo = null)
    {
        return $this->actingAs($usuario, 'sanctum')->post('/api/recargas', array_merge([
            'amount_bs' => 4000,
            'bank' => 'Banesco',
            'reference' => '123456789',
            'transferred_at' => today()->toDateString(),
            'comprobante' => $archivo ?? UploadedFile::fake()->image('comprobante.jpg'),
        ], $extra));
    }

    public function test_el_pasajero_aprobado_solicita_una_recarga_con_comprobante(): void
    {
        $pasajero = $this->pasajeroAprobado();

        $this->solicitar($pasajero)
            ->assertCreated()
            ->assertJsonPath('data.recharge.status', 'pending')
            ->assertJsonPath('data.recharge.amount_bs', 4000)
            ->assertJsonPath('data.recharge.reference', '123456789');

        $recarga = Recharge::query()->firstOrFail();

        $this->assertSame($pasajero->id, $recarga->user_id);
        $this->assertSame(RechargeStatus::Pending, $recarga->status);
        $this->assertNull($recarga->amount_usd, 'Todavía no hay tasa aplicada.');
        Storage::disk('local')->assertExists($recarga->proof_path);
    }

    public function test_una_cuenta_en_espera_no_puede_recargar(): void
    {
        $pasajero = User::factory()->passenger()->create(); // pending

        $this->solicitar($pasajero)->assertForbidden();

        $this->assertDatabaseCount('recharges', 0);
    }

    public function test_la_recarga_exige_comprobante(): void
    {
        $pasajero = $this->pasajeroAprobado();

        $this->actingAs($pasajero, 'sanctum')
            ->post('/api/recargas', [
                'amount_bs' => 4000,
                'bank' => 'Banesco',
                'reference' => '123456789',
                'transferred_at' => today()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comprobante');

        $this->assertDatabaseCount('recharges', 0);
    }

    public function test_la_fecha_de_la_transferencia_no_puede_ser_futura(): void
    {
        $pasajero = $this->pasajeroAprobado();

        $this->solicitar($pasajero, ['transferred_at' => today()->addDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transferred_at');
    }

    public function test_aprobar_la_recarga_aplica_la_tasa_vigente_y_acredita_el_saldo(): void
    {
        $pasajero = $this->pasajeroAprobado();
        ExchangeRate::factory()->create(['rate' => 40, 'effective_date' => today()->toDateString()]);
        $recarga = Recharge::factory()->for($pasajero)->create(['amount_bs' => 4000]);
        $auditor = User::factory()->auditor()->create();

        $aprobada = app(AprobarRecarga::class)->ejecutar($recarga, $auditor);

        $this->assertSame(RechargeStatus::Approved, $aprobada->status);
        $this->assertSame('40.0000', $aprobada->exchange_rate);
        $this->assertSame('100.00', $aprobada->amount_usd);

        $billetera = $pasajero->fresh()->wallet;
        $this->assertSame('100.00', $billetera->balance);

        $movimiento = $billetera->transactions()->firstOrFail();
        $this->assertSame(WalletTransactionType::Recharge, $movimiento->type);
        $this->assertSame('credit', $movimiento->direction);
        $this->assertSame('100.00', $movimiento->amount);
        $this->assertSame($auditor->id, $movimiento->created_by);
    }

    public function test_el_auditor_puede_cambiar_la_tasa_solo_para_esa_aprobacion(): void
    {
        $pasajero = $this->pasajeroAprobado();
        ExchangeRate::factory()->create(['rate' => 40]);
        $recarga = Recharge::factory()->for($pasajero)->create(['amount_bs' => 4000]);
        $auditor = User::factory()->auditor()->create();

        app(AprobarRecarga::class)->ejecutar($recarga, $auditor, 50);

        $this->assertSame('50.0000', $recarga->fresh()->exchange_rate);
        $this->assertSame('80.00', $recarga->fresh()->amount_usd);
        $this->assertSame('80.00', $pasajero->fresh()->wallet->balance);
    }

    public function test_no_se_puede_aprobar_sin_una_tasa_de_cambio(): void
    {
        $recarga = Recharge::factory()->create();
        $auditor = User::factory()->auditor()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay una tasa de cambio vigente');

        app(AprobarRecarga::class)->ejecutar($recarga, $auditor);
    }

    public function test_una_recarga_ya_revisada_no_se_puede_aprobar_dos_veces(): void
    {
        $recarga = Recharge::factory()->approved()->create();
        $auditor = User::factory()->auditor()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya fue revisada');

        app(AprobarRecarga::class)->ejecutar($recarga, $auditor, 40);
    }

    public function test_rechazar_una_recarga_no_acredita_saldo(): void
    {
        $pasajero = $this->pasajeroAprobado();
        $recarga = Recharge::factory()->for($pasajero)->create();
        $auditor = User::factory()->auditor()->create();

        app(RechazarRecarga::class)->ejecutar($recarga, $auditor, 'El comprobante no coincide con el monto.');

        $this->assertSame(RechargeStatus::Rejected, $recarga->fresh()->status);
        $this->assertSame('El comprobante no coincide con el monto.', $recarga->fresh()->rejection_reason);
        $this->assertSame('0.00', $pasajero->fresh()->wallet?->balance ?? '0.00');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_el_panel_avisa_cuando_la_referencia_ya_se_uso(): void
    {
        Recharge::factory()->create(['bank' => 'Banesco', 'reference' => 'ABC-123']);
        $nueva = Recharge::factory()->create(['bank' => 'banesco', 'reference' => 'abc-123']);

        $this->assertTrue($nueva->referenceWasUsedBefore());
    }

    public function test_la_referencia_se_guarda_en_mayusculas(): void
    {
        $recarga = Recharge::factory()->create(['reference' => '  ab-99 ']);

        $this->assertSame('AB-99', $recarga->fresh()->reference);
    }
}
