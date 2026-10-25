<?php

namespace Tests\Feature\Panel;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = User::factory()->auditor()->create();
    }

    public function test_exportar_reporte_de_viajes_en_pdf(): void
    {
        $pasajero = User::factory()->create(['role' => UserRole::Passenger, 'status' => UserStatus::Approved]);
        $conductor = User::factory()->create(['role' => UserRole::Driver, 'status' => UserStatus::Approved]);

        Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::Completed,
            'completed_at' => now(),
            'total' => 12.50,
            'commission_amount' => 1.88,
            'driver_earning' => 10.62,
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.reportes.viajes.pdf', [
                'desde' => now()->startOfMonth()->toDateString(),
                'hasta' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_exportar_reporte_de_movimientos_en_pdf(): void
    {
        $user = User::factory()->create(['role' => UserRole::Passenger, 'status' => UserStatus::Approved]);
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 20.00, 'held_balance' => 0]);

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::Recharge,
            'direction' => 'credit',
            'amount' => 20.00,
            'balance_after' => 20.00,
            'description' => 'Recarga aprobada',
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.reportes.movimientos.pdf', [
                'desde' => now()->startOfMonth()->toDateString(),
                'hasta' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_exportar_historial_en_pdf(): void
    {
        activity('pasajeros')
            ->causedBy($this->auditor)
            ->log('Prueba de evento para el reporte');

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.historial.export.pdf'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
