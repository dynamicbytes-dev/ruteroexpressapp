<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use App\Enums\VehicleType;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El pasajero elige moto o carro; cada tipo tiene su tarifa y sus conductores.
 */
class VehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));

        ExchangeRate::factory()->create([
            'effective_date' => '2026-10-19',
            'rate' => 40,
        ]);

        $this->mock(RouteEstimator::class, function ($mock): void {
            $mock->shouldReceive('estimar')->andReturn([
                'distance_km' => 5.0,
                'duration_minutes' => 12,
                'source' => 'fake',
            ]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function datos(string $tipo = 'moto'): array
    {
        return [
            'origin_lat' => 10.4961,
            'origin_lng' => -66.8517,
            'destination_lat' => 10.5003,
            'destination_lng' => -66.8428,
            'origin_address' => 'Av. Francisco de Miranda',
            'destination_address' => 'Plaza Venezuela',
            'payment_method' => 'wallet',
            'vehicle_type' => $tipo,
        ];
    }

    public function test_el_carro_cuesta_exactamente_un_dolar_mas_que_la_moto(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 100]);

        $moto = $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes/cotizar', $this->datos('moto'))
            ->assertOk()
            ->assertJsonPath('data.vehicle_type', 'moto');

        $carro = $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes/cotizar', $this->datos('carro'))
            ->assertOk()
            ->assertJsonPath('data.vehicle_type', 'carro')
            ->assertJsonPath('data.vehicle_surcharge', 1);

        $this->assertSame(
            round((float) $moto->json('data.total') + 1, 2),
            (float) $carro->json('data.total'),
        );
    }

    public function test_si_la_app_vieja_no_envia_tipo_se_asume_moto(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 100]);

        $datos = $this->datos();
        unset($datos['vehicle_type']);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes/cotizar', $datos)
            ->assertOk()
            ->assertJsonPath('data.vehicle_type', 'moto')
            ->assertJsonPath('data.vehicle_surcharge', 0);
    }

    public function test_el_viaje_guarda_el_tipo_y_solo_avisa_a_conductores_del_mismo_tipo(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 100]);

        $conductorMoto = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductorMoto)->locatedAt(10.4961, -66.8517)->create([
            'vehicle_type' => VehicleType::Motorcycle,
        ]);
        Wallet::factory()->for($conductorMoto)->create(['balance' => 0]);

        $conductorCarro = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductorCarro)->locatedAt(10.4961, -66.8517)->create([
            'vehicle_type' => VehicleType::Car,
        ]);
        Wallet::factory()->for($conductorCarro)->create(['balance' => 0]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datos('carro'))
            ->assertCreated()
            ->assertJsonPath('data.trip.vehicle_type', 'carro')
            ->assertJsonPath('data.trip.vehicle_type_label', 'Carro');

        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->assertSame(VehicleType::Car, $viaje->vehicle_type);
        $this->assertSame($conductorCarro->id, $oferta->driver_id);
        $this->assertNotSame($conductorMoto->id, $oferta->driver_id);
    }
}
