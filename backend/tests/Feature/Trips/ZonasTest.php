<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use App\Models\City;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada ciudad es una zona independiente: tarifas propias y conductores que no
 * reciben viajes de otras zonas.
 */
class ZonasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ExchangeRate::factory()->create([
            'effective_date' => today()->toDateString(),
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

    private function ciudad(string $slug): City
    {
        return City::query()->where('slug', $slug)->firstOrFail();
    }

    private function pasajeroEn(City $ciudad): User
    {
        $pasajero = User::factory()->passenger()->approved()->create([
            'city_id' => $ciudad->id,
        ]);

        Wallet::factory()->for($pasajero)->create(['balance' => 100]);

        return $pasajero;
    }

    private function conductorEn(City $ciudad, float $lng = -64.6325000): User
    {
        $conductor = User::factory()->driver()->approved()->create([
            'city_id' => $ciudad->id,
        ]);

        DriverProfile::factory()
            ->for($conductor)
            ->locatedAt(10.2139000, $lng)
            ->create(['city_id' => $ciudad->id]);

        Wallet::factory()->for($conductor)->create(['balance' => 0]);

        return $conductor;
    }

    /** @return array<string, mixed> */
    private function datosViaje(): array
    {
        return [
            'origin_lat' => 10.2139,
            'origin_lng' => -64.6325,
            'destination_lat' => 10.2200,
            'destination_lng' => -64.6300,
            'origin_address' => 'Av. Municipal, Puerto La Cruz',
            'destination_address' => 'Paseo Colón, Puerto La Cruz',
            'payment_method' => 'wallet',
        ];
    }

    public function test_un_viaje_solo_se_ofrece_a_conductores_de_su_zona(): void
    {
        $barcelona = $this->ciudad('barcelona');
        $maturin = $this->ciudad('maturin');

        $pasajero = $this->pasajeroEn($barcelona);
        $conductorLocal = $this->conductorEn($barcelona, -64.6325000);
        $conductorOtraZona = $this->conductorEn($maturin, -64.6324000);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated()
            ->assertJsonPath('data.trip.city.name', 'Barcelona')
            ->assertJsonPath('data.trip.status', 'offered');

        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->assertSame($barcelona->id, $viaje->city_id);
        $this->assertSame($conductorLocal->id, $oferta->driver_id);
        $this->assertNotSame($conductorOtraZona->id, $oferta->driver_id);

        $this->actingAs($conductorOtraZona, 'sanctum')
            ->getJson('/api/conductor/ofertas')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_la_tarifa_se_toma_de_la_ciudad_del_pasajero(): void
    {
        $barcelona = $this->ciudad('barcelona');
        $cumana = $this->ciudad('cumana');

        Configuracion::put('tarifa_base', 2.50, $barcelona->id);
        Configuracion::put('tarifa_base', 6.00, $cumana->id);

        $pasajero = $this->pasajeroEn($barcelona);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes/cotizar', $this->datosViaje())
            ->assertOk()
            ->assertJsonPath('data.base_fare', 2.5)
            ->assertJsonPath('data.city_id', $barcelona->id);

        // La cotización de otra zona conserva su propio precio base.
        $pasajeroCumana = $this->pasajeroEn($cumana);

        $this->actingAs($pasajeroCumana, 'sanctum')
            ->postJson('/api/viajes/cotizar', $this->datosViaje())
            ->assertOk()
            ->assertJsonPath('data.base_fare', 6);
    }
}
