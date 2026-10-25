<?php

namespace Tests\Feature\Wallet;

use App\Domain\Wallet\Actions\PagarRetiro;
use App\Domain\Wallet\Actions\RechazarRetiro;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Retiros de conductores: solicitud con retención inmediata y pago del auditor.
 */
class RetirosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function conductorConSaldo(float $saldo = 20): User
    {
        $conductor = User::factory()->driver()->approved()->create();
        Wallet::factory()->for($conductor)->create(['balance' => $saldo, 'held_balance' => 0]);

        return $conductor;
    }

    private function solicitar(User $conductor, array $extra = [])
    {
        return $this->actingAs($conductor, 'sanctum')->post('/api/retiros', array_merge([
            'amount_usd' => 10,
            'bank' => 'Banesco',
            'account_number' => '01340000000000000000',
            'account_holder' => 'Pedro Rojas',
            'account_id' => '14988776',
        ], $extra));
    }

    public function test_el_conductor_solicita_un_retiro_y_el_monto_queda_retenido(): void
    {
        $conductor = $this->conductorConSaldo(20);

        $this->solicitar($conductor)
            ->assertCreated()
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonPath('data.withdrawal.amount_usd', 10);

        $retiro = Withdrawal::query()->firstOrFail();
        $billetera = $conductor->fresh()->wallet;

        $this->assertSame(WithdrawalStatus::Pending, $retiro->status);
        $this->assertSame('20.00', $billetera->balance, 'El saldo total no cambia todavía.');
        $this->assertSame('10.00', $billetera->held_balance);
        $this->assertSame(10.0, $billetera->available());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_no_puede_retirar_mas_de_lo_disponible(): void
    {
        $conductor = $this->conductorConSaldo(8);

        $this->solicitar($conductor, ['amount_usd' => 10])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No tienes saldo suficiente: disponible $8.00 y solicitaste $10.00.');

        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertSame('0.00', $conductor->fresh()->wallet->held_balance);
    }

    public function test_un_pasajero_no_puede_retirar(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 20]);

        $this->solicitar($pasajero)->assertForbidden();
        $this->assertDatabaseCount('withdrawals', 0);
    }

    public function test_un_conductor_en_espera_no_puede_retirar(): void
    {
        $conductor = User::factory()->driver()->create();
        Wallet::factory()->for($conductor)->create(['balance' => 20]);

        $this->solicitar($conductor)->assertForbidden();
    }

    public function test_el_retiro_minimo_es_configurable(): void
    {
        $conductor = $this->conductorConSaldo(20);

        $this->solicitar($conductor, ['amount_usd' => 0.5])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El retiro mínimo es de $1.00.');
    }

    public function test_pagar_el_retiro_descuenta_el_saldo_y_deja_el_movimiento(): void
    {
        $conductor = $this->conductorConSaldo(20);
        $retiro = Withdrawal::factory()->for($conductor)->create(['amount_usd' => 10]);
        $conductor->wallet->update(['held_balance' => 10]);
        $auditor = User::factory()->auditor()->create();

        app(PagarRetiro::class)->ejecutar(
            $retiro,
            $auditor,
            UploadedFile::fake()->image('transferencia.jpg'),
        );

        $retiro->refresh();
        $billetera = $conductor->fresh()->wallet;

        $this->assertSame(WithdrawalStatus::Paid, $retiro->status);
        $this->assertSame('10.00', $billetera->balance);
        $this->assertSame('0.00', $billetera->held_balance);
        Storage::disk('local')->assertExists($retiro->proof_path);

        $movimiento = $billetera->transactions()->firstOrFail();
        $this->assertSame(WalletTransactionType::Withdrawal, $movimiento->type);
        $this->assertSame('debit', $movimiento->direction);
        $this->assertSame('10.00', $movimiento->amount);
    }

    public function test_rechazar_el_retiro_libera_el_monto(): void
    {
        $conductor = $this->conductorConSaldo(20);
        $retiro = Withdrawal::factory()->for($conductor)->create(['amount_usd' => 10]);
        $conductor->wallet->update(['held_balance' => 10]);
        $auditor = User::factory()->auditor()->create();

        app(RechazarRetiro::class)->ejecutar($retiro, $auditor, 'La cuenta no está a nombre del conductor.');

        $billetera = $conductor->fresh()->wallet;

        $this->assertSame(WithdrawalStatus::Rejected, $retiro->fresh()->status);
        $this->assertSame('20.00', $billetera->balance);
        $this->assertSame('0.00', $billetera->held_balance);
        $this->assertSame(20.0, $billetera->available());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_un_retiro_ya_pagado_no_se_procesa_dos_veces(): void
    {
        $conductor = $this->conductorConSaldo(20);
        $retiro = Withdrawal::factory()->for($conductor)->paid()->create(['amount_usd' => 10]);
        $auditor = User::factory()->auditor()->create();

        $this->expectExceptionMessage('ya fue procesado');

        app(PagarRetiro::class)->ejecutar(
            $retiro,
            $auditor,
            UploadedFile::fake()->image('transferencia.jpg'),
        );
    }
}
