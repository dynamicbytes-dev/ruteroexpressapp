<?php

namespace Tests\Feature\Panel;

use App\Enums\PaymentMethod;
use App\Enums\TripStatus;
use App\Enums\WalletTransactionType;
use App\Models\Recharge;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportesPanelTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_un_pasajero_no_entra_a_los_reportes(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/reportes')->assertForbidden();
        $this->actingAs($pasajero)->get('/panel/reportes/viajes.csv')->assertForbidden();
    }

    public function test_el_auditor_ve_las_metricas_del_periodo(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        $conductor = User::factory()->driver()->approved()->create();

        Trip::factory()
            ->assignedTo($conductor)
            ->create([
                'passenger_id' => $pasajero->id,
                'status' => TripStatus::Completed,
                'payment_method' => PaymentMethod::Cash,
                'total' => 10,
                'commission_amount' => 1,
                'driver_earning' => 9,
                'completed_at' => now(),
            ]);

        Recharge::factory()->approved(40, $auditor)->create([
            'amount_bs' => 400,
            'amount_usd' => 10,
            'reviewed_at' => now(),
        ]);

        Withdrawal::factory()->paid($auditor)->create([
            'user_id' => $conductor->id,
            'amount_usd' => 5,
            'reviewed_at' => now(),
        ]);

        $billetera = Wallet::factory()->for($conductor)->saldo(9)->create();

        WalletTransaction::create([
            'wallet_id' => $billetera->id,
            'type' => WalletTransactionType::Adjustment,
            'direction' => 'credit',
            'amount' => 2,
            'balance_after' => 11,
            'held_after' => 0,
            'description' => 'Ajuste de prueba',
            'created_by' => $auditor->id,
        ]);

        $this->actingAs($auditor)
            ->get('/panel/reportes')
            ->assertOk()
            ->assertSee('Viajes completados')
            ->assertSee('$10.00')
            ->assertSee('$1.00')
            ->assertSee('$9.00')
            ->assertSee('Ajustes manuales')
            ->assertSee('Efectivo')
            ->assertSee($conductor->first_name);
    }

    public function test_el_reporte_excluye_los_viajes_fuera_del_rango(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create();

        Trip::factory()
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::Completed,
                'total' => 99,
                'completed_at' => now()->subMonths(2),
            ]);

        $this->actingAs($auditor)
            ->get('/panel/reportes?desde='.today()->toDateString().'&hasta='.today()->toDateString())
            ->assertOk()
            ->assertDontSee('$99.00');
    }

    public function test_el_auditor_exporta_viajes_y_movimientos(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create(['first_name' => 'Exportado']);
        $pasajero = User::factory()->passenger()->approved()->create(['first_name' => 'Pasajero Uno']);

        Trip::factory()
            ->assignedTo($conductor)
            ->create([
                'passenger_id' => $pasajero->id,
                'status' => TripStatus::Completed,
                'origin_address' => 'Origen de prueba',
                'destination_address' => 'Destino de prueba',
                'total' => 10,
                'completed_at' => now(),
            ]);

        $billetera = Wallet::factory()->for($conductor)->create();

        WalletTransaction::create([
            'wallet_id' => $billetera->id,
            'type' => WalletTransactionType::Adjustment,
            'direction' => 'credit',
            'amount' => 2,
            'balance_after' => 2,
            'held_after' => 0,
            'description' => 'Movimiento exportado',
            'created_by' => $auditor->id,
        ]);

        $viajes = $this->actingAs($auditor)->get('/panel/reportes/viajes.csv');
        $viajes->assertOk();
        $viajes->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Origen de prueba', $viajes->streamedContent());
        $this->assertStringContainsString('Exportado', $viajes->streamedContent());

        $movimientos = $this->actingAs($auditor)->get('/panel/reportes/movimientos.csv');
        $movimientos->assertOk();
        $this->assertStringContainsString('Movimiento exportado', $movimientos->streamedContent());
    }
}
