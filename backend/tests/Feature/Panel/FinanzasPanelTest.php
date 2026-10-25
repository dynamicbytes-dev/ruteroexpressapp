<?php

namespace Tests\Feature\Panel;

use App\Enums\RechargeStatus;
use App\Enums\WithdrawalStatus;
use App\Models\ExchangeRate;
use App\Models\Recharge;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Support\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Pantallas de F2 en el panel del auditor: recargas, retiros, tasa y
 * configuración.
 */
class FinanzasPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Configuracion::flush();
    }

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_un_pasajero_no_entra_a_las_pantallas_de_finanzas(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/recargas')->assertForbidden();
        $this->actingAs($pasajero)->get('/panel/retiros')->assertForbidden();
        $this->actingAs($pasajero)->get('/panel/tasa')->assertForbidden();
        $this->actingAs($pasajero)->get('/panel/configuracion')->assertForbidden();
    }

    public function test_el_auditor_aprueba_una_recarga_desde_el_panel(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        ExchangeRate::factory()->create(['rate' => 40]);
        $recarga = Recharge::factory()->for($pasajero)->create(['amount_bs' => 4000]);

        $this->actingAs($auditor)
            ->post('/panel/recargas/'.$recarga->id.'/aprobar', ['exchange_rate' => 50])
            ->assertRedirect('/panel/recargas/'.$recarga->id)
            ->assertSessionHas('status');

        $this->assertSame(RechargeStatus::Approved, $recarga->fresh()->status);
        $this->assertSame('80.00', $pasajero->fresh()->wallet->balance);
    }

    public function test_el_auditor_rechaza_una_recarga_con_motivo(): void
    {
        $auditor = $this->auditor();
        $recarga = Recharge::factory()->create();

        $this->actingAs($auditor)
            ->post('/panel/recargas/'.$recarga->id.'/rechazar', ['reason' => 'Corto.'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($auditor)
            ->post('/panel/recargas/'.$recarga->id.'/rechazar', [
                'reason' => 'El comprobante está ilegible y no permite verificar la referencia.',
            ])
            ->assertSessionHas('status');

        $this->assertSame(RechargeStatus::Rejected, $recarga->fresh()->status);
    }

    public function test_el_auditor_paga_un_retiro_con_comprobante(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create();
        Wallet::factory()->for($conductor)->create(['balance' => 20, 'held_balance' => 10]);
        $retiro = Withdrawal::factory()->for($conductor)->create(['amount_usd' => 10]);

        $this->actingAs($auditor)
            ->post('/panel/retiros/'.$retiro->id.'/pagar', [
                'proof' => UploadedFile::fake()->image('transferencia.jpg'),
            ])
            ->assertRedirect('/panel/retiros/'.$retiro->id)
            ->assertSessionHas('status');

        $this->assertSame(WithdrawalStatus::Paid, $retiro->fresh()->status);
        $this->assertSame('10.00', $conductor->fresh()->wallet->balance);
        $this->assertSame('0.00', $conductor->fresh()->wallet->held_balance);
    }

    public function test_pagar_un_retiro_sin_comprobante_falla(): void
    {
        $auditor = $this->auditor();
        $retiro = Withdrawal::factory()->create();

        $this->actingAs($auditor)
            ->post('/panel/retiros/'.$retiro->id.'/pagar', [])
            ->assertSessionHasErrors('proof');

        $this->assertSame(WithdrawalStatus::Pending, $retiro->fresh()->status);
    }

    public function test_los_comprobantes_solo_se_abren_con_enlace_firmado(): void
    {
        $auditor = $this->auditor();
        $recarga = Recharge::factory()->create();
        Storage::disk('local')->put($recarga->proof_path, 'comprobante de prueba');

        $this->actingAs($auditor)
            ->get('/panel/recargas/'.$recarga->id.'/comprobante')
            ->assertForbidden();

        $url = URL::temporarySignedRoute(
            'panel.recargas.comprobante',
            now()->addMinutes(5),
            ['recharge' => $recarga->id],
        );

        $this->actingAs($auditor)->get($url)->assertOk();
    }

    public function test_el_auditor_carga_la_tasa_y_se_conserva_el_historico(): void
    {
        $auditor = $this->auditor();
        ExchangeRate::factory()->create(['rate' => 38, 'effective_date' => today()->toDateString()]);

        $this->actingAs($auditor)
            ->post('/panel/tasa', [
                'effective_date' => today()->toDateString(),
                'rate' => 41.5,
            ])
            ->assertRedirect('/panel/tasa')
            ->assertSessionHas('status');

        $this->assertDatabaseCount('exchange_rates', 2);
        $this->assertSame('41.5000', ExchangeRate::vigente()->rate);
        $this->assertSame($auditor->id, ExchangeRate::vigente()->created_by);
    }

    public function test_la_tasa_no_puede_tener_fecha_futura(): void
    {
        $auditor = $this->auditor();

        $this->actingAs($auditor)
            ->post('/panel/tasa', [
                'effective_date' => today()->addDay()->toDateString(),
                'rate' => 40,
            ])
            ->assertSessionHasErrors('effective_date');
    }

    public function test_el_auditor_guarda_la_configuracion_operativa(): void
    {
        $auditor = $this->auditor();

        $datos = $this->payloadConfiguracion([
            'tarifa_base' => '2.50',
            'precio_km' => '0.50',
            'radios_km' => '4, 6, 10',
            'tope_saldo_negativo' => '-10',
            'banco_nombre' => 'Mercantil',
        ]);

        $this->actingAs($auditor)
            ->put('/panel/configuracion', $datos)
            ->assertRedirect('/panel/configuracion')
            ->assertSessionHas('status');

        $this->assertSame(2.5, Configuracion::decimal('tarifa_base'));
        $this->assertSame(0.5, Configuracion::decimal('precio_km'));
        $this->assertSame([4, 6, 10], Configuracion::json('radios_km'));
        $this->assertSame(-10.0, Configuracion::decimal('tope_saldo_negativo'));
        $this->assertSame('Mercantil', Configuracion::get('banco_nombre'));
    }

    public function test_la_configuracion_valida_el_rango_de_la_dinamica_y_los_radios(): void
    {
        $auditor = $this->auditor();

        $this->actingAs($auditor)
            ->put('/panel/configuracion', $this->payloadConfiguracion([
                'dinamica_min' => '2',
                'dinamica_max' => '1.2',
                'radios_km' => 'solo uno',
            ]))
            ->assertSessionHasErrors(['dinamica_max', 'radios_km']);
    }

    public function test_el_dashboard_muestra_las_colas_reales_de_f2(): void
    {
        $auditor = $this->auditor();
        Recharge::factory()->count(2)->create();
        Withdrawal::factory()->create();

        $this->actingAs($auditor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Recargas por aprobar')
            ->assertSee('Retiros solicitados');
    }

    public function test_las_pantallas_de_f2_cargan_con_datos(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 10]);

        $recarga = Recharge::factory()->for($pasajero)->create();
        $retiro = Withdrawal::factory()->create();
        ExchangeRate::factory()->create(['rate' => 40]);

        $this->actingAs($auditor)->get('/panel/recargas')->assertOk()->assertSee($recarga->reference);
        $this->actingAs($auditor)->get('/panel/recargas/'.$recarga->id)->assertOk()->assertSee('Aprobar recarga');
        $this->actingAs($auditor)->get('/panel/retiros')->assertOk()->assertSee($retiro->account_number);
        $this->actingAs($auditor)->get('/panel/retiros/'.$retiro->id)->assertOk()->assertSee('Marcar como pagado');
        $this->actingAs($auditor)->get('/panel/tasa')->assertOk()->assertSee('Histórico');
        $this->actingAs($auditor)->get('/panel/configuracion')->assertOk()->assertSee('Tarifas y comisión');
    }

    /** @return array<string, string> */
    private function payloadConfiguracion(array $cambios = []): array
    {
        $datos = [];

        foreach (Configuracion::defaults() as $key => $default) {
            $valor = $default['value'];
            $datos[$key] = is_array($valor) ? implode(', ', $valor) : (string) $valor;
        }

        foreach ($cambios as $key => $valor) {
            $datos[$key] = $valor;
        }

        return $datos;
    }
}
