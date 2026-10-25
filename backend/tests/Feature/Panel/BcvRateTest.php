<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BcvRateTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = User::factory()->create([
            'role' => UserRole::Auditor,
            'status' => UserStatus::Approved,
        ]);
    }

    public function test_pantalla_de_tasa_muestra_opcion_bcv(): void
    {
        Http::fake([
            've.dolarapi.com/*' => Http::response([
                'moneda' => 'USD',
                'fuente' => 'oficial',
                'promedio' => 48.7500,
                'fechaActualizacion' => today()->toDateString(),
            ], 200),
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.tasa.index'));

        $response->assertOk()
            ->assertSee('API Banco Central (BCV)')
            ->assertSee('Sincronizar tasa oficial BCV');
    }

    public function test_el_auditor_sincroniza_tasa_oficial_con_bcv(): void
    {
        Http::fake([
            've.dolarapi.com/*' => Http::response([
                'moneda' => 'USD',
                'fuente' => 'oficial',
                'promedio' => 52.3400,
                'fechaActualizacion' => today()->toDateString(),
            ], 200),
        ]);

        $response = $this->actingAs($this->auditor)
            ->post(route('panel.tasa.bcv'));

        $response->assertRedirect(route('panel.tasa.index'))
            ->assertSessionHas('status');

        $vigente = ExchangeRate::vigente();
        $this->assertNotNull($vigente);
        $this->assertEquals(52.3400, (float) $vigente->rate);
    }
}
