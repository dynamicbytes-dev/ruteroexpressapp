<?php

namespace Tests\Feature\Api;

use App\Domain\Wallet\WalletService;
use App\Enums\WalletTransactionType;
use App\Models\ExchangeRate;
use App\Models\Recharge;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de la billetera para la app móvil.
 */
class BilleteraApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_billetera_devuelve_saldo_tasa_datos_bancarios_y_movimientos(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $billetera = Wallet::factory()->for($pasajero)->create([
            'balance' => 50,
            'held_balance' => 5,
        ]);
        ExchangeRate::factory()->create(['rate' => 40]);

        app(WalletService::class)->acreditar(
            $billetera,
            12.5,
            WalletTransactionType::Adjustment,
            'Ajuste de prueba',
        );

        $this->actingAs($pasajero, 'sanctum')
            ->get('/api/billetera')
            ->assertOk()
            ->assertJsonPath('data.balance', 62.5)
            ->assertJsonPath('data.held', 5)
            ->assertJsonPath('data.available', 57.5)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.exchange_rate.rate', 40)
            ->assertJsonPath('data.exchange_rate.local_equivalent', 2500)
            ->assertJsonPath('data.limits.min_withdrawal_usd', 1)
            ->assertJsonPath('data.bank.bank', 'Banesco')
            ->assertJsonPath('data.recent_transactions.0.type', 'adjustment')
            ->assertJsonPath('data.recent_transactions.0.signed_amount', 12.5);
    }

    public function test_sin_tasa_cargada_la_billetera_lo_informa_como_nulo(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->get('/api/billetera')
            ->assertOk()
            ->assertJsonPath('data.exchange_rate', null);
    }

    public function test_el_historial_de_movimientos_esta_paginado(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $billetera = Wallet::factory()->for($pasajero)->create(['balance' => 10]);

        foreach ([1, 2, 3] as $monto) {
            app(WalletService::class)->acreditar(
                $billetera,
                $monto,
                WalletTransactionType::Adjustment,
                "Ajuste {$monto}",
            );
        }

        $this->actingAs($pasajero, 'sanctum')
            ->get('/api/billetera/movimientos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.signed_amount', 3);
    }

    public function test_cada_persona_solo_ve_sus_recargas_y_retiros(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();

        Recharge::factory()->for($pasajero)->create(['reference' => 'MIA1']);
        Recharge::factory()->for($otro)->create(['reference' => 'AJENA1']);

        $this->actingAs($pasajero, 'sanctum')
            ->get('/api/recargas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'MIA1')
            ->assertJsonPath('data.0.status', 'pending');

        $conductor = User::factory()->driver()->approved()->create();
        Withdrawal::factory()->for($conductor)->create(['amount_usd' => 7]);

        $this->actingAs($conductor, 'sanctum')
            ->get('/api/retiros')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount_usd', 7)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_la_tasa_vigente_se_expone_para_la_referencia_en_bolivares(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        ExchangeRate::factory()->create([
            'rate' => 39.25,
            'effective_date' => today()->subDay()->toDateString(),
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->get('/api/tasa-cambio')
            ->assertOk()
            ->assertJsonPath('data.rate', 39.25)
            ->assertJsonPath('data.effective_date', today()->subDay()->toDateString());
    }
}
