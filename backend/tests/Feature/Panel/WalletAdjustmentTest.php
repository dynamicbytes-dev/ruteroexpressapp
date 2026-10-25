<?php

namespace Tests\Feature\Panel;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ajuste manual de saldo desde la ficha del usuario en el panel.
 */
class WalletAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_el_auditor_suma_saldo_con_una_nota(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 10]);

        $this->actingAs($auditor)
            ->from(route('panel.usuarios.show', $pasajero))
            ->post(route('panel.usuarios.ajuste', $pasajero), [
                'amount' => '5.50',
                'note' => 'Compensación por un cobro duplicado.',
            ])
            ->assertRedirect(route('panel.usuarios.show', $pasajero))
            ->assertSessionHas('status');

        $this->assertSame('15.50', $pasajero->fresh()->wallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $pasajero->wallet->id,
            'type' => WalletTransactionType::Adjustment->value,
            'direction' => 'credit',
            'amount' => 5.50,
            'created_by' => $auditor->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'billetera',
            'description' => 'Ajuste manual de saldo',
            'subject_id' => $pasajero->id,
            'causer_id' => $auditor->id,
        ]);
    }

    public function test_el_auditor_resta_saldo_con_una_nota(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 10]);

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.ajuste', $pasajero), [
                'amount' => '-2.25',
                'note' => 'Devolución de un ajuste cargado por error.',
            ])
            ->assertSessionHas('status');

        $this->assertSame('7.75', $pasajero->fresh()->wallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $pasajero->wallet->id,
            'type' => WalletTransactionType::Adjustment->value,
            'direction' => 'debit',
            'amount' => 2.25,
        ]);
    }

    public function test_el_ajuste_exige_monto_y_nota(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.ajuste', $pasajero), [])
            ->assertSessionHasErrors(['amount', 'note']);

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.ajuste', $pasajero), [
                'amount' => '1',
                'note' => 'corto',
            ])
            ->assertSessionHasErrors('note');
    }

    public function test_el_ajuste_no_puede_ser_de_cero(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.ajuste', $pasajero), [
                'amount' => '0.00',
                'note' => 'Un ajuste sin efecto no tiene sentido.',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_no_se_ajusta_el_saldo_de_un_auditor(): void
    {
        $auditor = $this->auditor();
        $otroAuditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->post(route('panel.usuarios.ajuste', $otroAuditor), [
                'amount' => '10',
                'note' => 'Intento de ajuste a una cuenta de auditoría.',
            ])
            ->assertForbidden();
    }

    public function test_la_ficha_muestra_el_saldo_y_el_formulario(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 42.25]);

        $this->actingAs($auditor)
            ->get(route('panel.usuarios.show', $pasajero))
            ->assertOk()
            ->assertSee('Billetera')
            ->assertSee('$42.25')
            ->assertSee('Ajustar saldo');
    }

    public function test_un_pasajero_no_puede_ajustar_saldos(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)
            ->post(route('panel.usuarios.ajuste', $otro), [
                'amount' => '10',
                'note' => 'Intento de ajuste sin ser auditor.',
            ])
            ->assertForbidden();
    }
}
