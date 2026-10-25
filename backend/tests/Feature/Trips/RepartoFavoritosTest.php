<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use App\Enums\TripOfferStatus;
use App\Enums\VehicleType;
use App\Models\DriverFavorite;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Configuracion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prioridad del conductor favorito en la cascada de asignación.
 *
 * El favorito solo gana el primer intento si está en línea, con ubicación
 * fresca, dentro del radio configurado y con el mismo tipo de vehículo.
 */
class RepartoFavoritosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00'));

        ExchangeRate::factory()->create([
            'effective_date' => '2026-10-21',
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

    private function pasajeroConSaldo(): User
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => 100]);

        return $pasajero;
    }

    private function conductorEn(
        float $lat,
        float $lng = -66.8517,
        VehicleType $tipo = VehicleType::Motorcycle,
    ): User {
        $conductor = User::factory()->driver()->approved()->create();

        DriverProfile::factory()
            ->for($conductor)
            ->locatedAt($lat, $lng)
            ->create(['vehicle_type' => $tipo]);

        Wallet::factory()->for($conductor)->create(['balance' => 0]);

        return $conductor;
    }

    /** @return array<string, mixed> */
    private function datosViaje(): array
    {
        return [
            'origin_lat' => 10.4961,
            'origin_lng' => -66.8517,
            'destination_lat' => 10.5003,
            'destination_lng' => -66.8428,
            'origin_address' => 'Av. Francisco de Miranda',
            'destination_address' => 'Plaza Venezuela',
            'payment_method' => 'wallet',
        ];
    }

    private function pedirViaje(User $pasajero): Trip
    {
        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        return Trip::query()->latest('id')->firstOrFail();
    }

    private function ofertaActual(Trip $viaje): TripOffer
    {
        return TripOffer::query()
            ->where('trip_id', $viaje->id)
            ->where('status', TripOfferStatus::Pending)
            ->latest('id')
            ->firstOrFail();
    }

    private function marcarFavorito(User $pasajero, User $conductor): void
    {
        DriverFavorite::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
        ]);
    }

    public function test_el_favorito_cercano_recibe_la_oferta_antes_que_el_mas_cercano(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favorito = $this->conductorEn(10.5501);

        $this->marcarFavorito($pasajero, $favorito);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($favorito->id, $viaje->driver_id);
        $this->assertSame($favorito->id, $this->ofertaActual($viaje)->driver_id);
        $this->assertNotSame($cercano->id, $viaje->driver_id);
    }

    public function test_sin_favoritos_gana_el_mas_cercano(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $this->conductorEn(10.5501);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_el_favorito_fuera_del_radio_configurado_no_tiene_prioridad(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favoritoLejos = $this->conductorEn(10.5861);

        $this->marcarFavorito($pasajero, $favoritoLejos);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_el_director_puede_desactivar_la_prioridad_con_radio_cero(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favorito = $this->conductorEn(10.5501);

        $this->marcarFavorito($pasajero, $favorito);
        Configuracion::put('radio_favorito_km', 0);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_el_favorito_desconectado_no_recibe_la_oferta(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favorito = $this->conductorEn(10.5501);

        $this->marcarFavorito($pasajero, $favorito);
        $favorito->driverProfile->forceFill([
            'is_online' => false,
            'went_online_at' => null,
        ])->save();

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_el_favorito_de_otro_pasajero_no_tiene_prioridad(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $otroPasajero = User::factory()->passenger()->approved()->create();
        $cercano = $this->conductorEn(10.4965);
        $favoritoAjeno = $this->conductorEn(10.5501);

        $this->marcarFavorito($otroPasajero, $favoritoAjeno);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_si_el_favorito_rechaza_la_cascada_sigue_con_el_mas_cercano(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favorito = $this->conductorEn(10.5501);

        $this->marcarFavorito($pasajero, $favorito);

        $viaje = $this->pedirViaje($pasajero);
        $primera = $this->ofertaActual($viaje);

        $this->assertSame($favorito->id, $primera->driver_id);

        $this->actingAs($favorito, 'sanctum')
            ->postJson("/api/ofertas/{$primera->id}/rechazar")
            ->assertOk();

        $viaje->refresh();

        $this->assertSame($cercano->id, $viaje->driver_id);
        $this->assertSame(2, TripOffer::query()->where('trip_id', $viaje->id)->count());
        $this->assertSame(
            $cercano->id,
            TripOffer::query()
                ->where('trip_id', $viaje->id)
                ->where('status', TripOfferStatus::Pending)
                ->value('driver_id'),
        );
    }

    public function test_el_favorito_con_otro_tipo_de_vehiculo_no_recibe_la_oferta(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favoritoCarro = $this->conductorEn(10.5501, tipo: VehicleType::Car);

        $this->marcarFavorito($pasajero, $favoritoCarro);

        $viaje = $this->pedirViaje($pasajero);

        $this->assertSame($cercano->id, $viaje->driver_id);
    }

    public function test_el_favorito_se_usa_solo_en_el_primer_intento(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        $cercano = $this->conductorEn(10.4965);
        $favorito = $this->conductorEn(10.5501);

        $this->marcarFavorito($pasajero, $favorito);

        // Primer viaje: gana el favorito y rechaza.
        $viaje = $this->pedirViaje($pasajero);
        $primera = $this->ofertaActual($viaje);

        $this->actingAs($favorito, 'sanctum')
            ->postJson("/api/ofertas/{$primera->id}/rechazar")
            ->assertOk();

        // La cascada no vuelve a ofrecerle al mismo favorito ya rechazado.
        $this->assertSame($cercano->id, $viaje->refresh()->driver_id);
    }
}
