<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use App\Enums\DocumentType;
use App\Enums\TripStatus;
use App\Events\NotificacionCreada;
use App\Events\OfertaViajeCreada;
use App\Events\UbicacionConductorActualizada;
use App\Events\ViajeActualizado;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\Wallet;
use App\Notifications\DocumentoPorVencer;
use Carbon\Carbon;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Señales de tiempo real que alimentan los canales privados de Reverb.
 *
 * Los eventos no llevan el viaje completo: la app pide el detalle a la API y
 * sigue viendo solo lo que su rol permite.
 */
class TiempoRealViajesTest extends TestCase
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

    private function pasajeroConSaldo(float $saldo = 100): User
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        Wallet::factory()->for($pasajero)->create(['balance' => $saldo]);

        return $pasajero;
    }

    /** @return array{0: User, 1: DriverProfile} */
    private function conductorCerca(float $lng = -66.8517000, float $saldo = 0): array
    {
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->locatedAt(10.4961000, $lng)->create();
        Wallet::factory()->for($conductor)->create(['balance' => $saldo]);

        return [$conductor, $perfil];
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

    /** @param  array<int, Channel>  $canales */
    private function nombresDeCanales(array $canales): array
    {
        return array_map(fn ($canal): string => $canal->name, $canales);
    }

    public function test_la_oferta_inicial_llega_al_conductor_por_su_canal(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        Event::fake([OfertaViajeCreada::class, ViajeActualizado::class]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        $oferta = TripOffer::query()->firstOrFail();

        Event::assertDispatched(
            OfertaViajeCreada::class,
            function (OfertaViajeCreada $event) use ($conductor, $oferta): bool {
                $datos = $event->broadcastWith();

                return $event->oferta->id === $oferta->id
                    && $event->broadcastAs() === 'oferta.viaje'
                    && $event->broadcastOn()->name === 'private-App.Models.User.'.$conductor->id
                    && $datos['trip_id'] === $oferta->trip_id
                    && $datos['offer_id'] === $oferta->id;
            },
        );

        Event::assertDispatched(
            ViajeActualizado::class,
            fn (ViajeActualizado $event): bool => $event->motivo === 'oferta_enviada'
                && in_array($pasajero->id, $event->usuarios, true),
        );
    }

    public function test_el_pasajero_y_el_conductor_reciben_la_aceptacion(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        $oferta = TripOffer::query()->firstOrFail();

        Event::fake([ViajeActualizado::class]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/ofertas/{$oferta->id}/aceptar")
            ->assertOk();

        Event::assertDispatched(
            ViajeActualizado::class,
            function (ViajeActualizado $event) use ($pasajero, $conductor): bool {
                if ($event->motivo !== 'aceptado' || $event->viaje->status !== TripStatus::Accepted) {
                    return false;
                }

                $canales = $this->nombresDeCanales($event->broadcastOn());

                return in_array('private-App.Models.User.'.$pasajero->id, $canales, true)
                    && in_array('private-App.Models.User.'.$conductor->id, $canales, true);
            },
        );
    }

    public function test_cada_cambio_de_estado_emite_un_evento(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/ofertas/{$oferta->id}/aceptar")
            ->assertOk();

        Event::fake([ViajeActualizado::class]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/llegada")
            ->assertOk();

        Event::assertDispatched(
            ViajeActualizado::class,
            fn (ViajeActualizado $event): bool => $event->motivo === 'estado'
                && $event->viaje->status === TripStatus::Arriving,
        );
    }

    public function test_la_cancelacion_del_pasajero_avisa_al_conductor_ofertado(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        $viaje = Trip::query()->firstOrFail();

        Event::fake([ViajeActualizado::class]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/cancelar")
            ->assertOk();

        Event::assertDispatched(
            ViajeActualizado::class,
            function (ViajeActualizado $event) use ($pasajero, $conductor): bool {
                if ($event->motivo !== 'cancelado' || $event->viaje->status !== TripStatus::Cancelled) {
                    return false;
                }

                $canales = $this->nombresDeCanales($event->broadcastOn());

                return in_array('private-App.Models.User.'.$pasajero->id, $canales, true)
                    && in_array('private-App.Models.User.'.$conductor->id, $canales, true);
            },
        );
    }

    public function test_la_cancelacion_del_conductor_avisa_al_pasajero(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated();

        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/ofertas/{$oferta->id}/aceptar")
            ->assertOk();

        Event::fake([ViajeActualizado::class]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/cancelar")
            ->assertOk();

        Event::assertDispatched(
            ViajeActualizado::class,
            function (ViajeActualizado $event) use ($pasajero, $conductor): bool {
                if ($event->motivo !== 'conductor_cancelo') {
                    return false;
                }

                $canales = $this->nombresDeCanales($event->broadcastOn());

                return in_array('private-App.Models.User.'.$pasajero->id, $canales, true)
                    && in_array('private-App.Models.User.'.$conductor->id, $canales, true);
            },
        );
    }

    public function test_la_ubicacion_del_conductor_llega_al_pasajero(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $viaje = Trip::factory()
            ->assignedTo($conductor)
            ->create(['passenger_id' => $pasajero->id]);

        Event::fake([UbicacionConductorActualizada::class]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/conductor/ubicacion', [
                'lat' => 10.4962,
                'lng' => -66.8518,
                'bearing' => 90,
                'speed_kmh' => 30,
            ])
            ->assertOk();

        Event::assertDispatched(
            UbicacionConductorActualizada::class,
            function (UbicacionConductorActualizada $event) use ($viaje, $pasajero): bool {
                $datos = $event->broadcastWith();

                return $event->viaje->id === $viaje->id
                    && $event->broadcastOn()->name === 'private-App.Models.User.'.$pasajero->id
                    && $datos['lat'] === 10.4962
                    && $datos['lng'] === -66.8518
                    && $datos['bearing'] === 90;
            },
        );
    }

    public function test_no_se_emite_ubicacion_si_el_conductor_no_tiene_viaje(): void
    {
        [$conductor] = $this->conductorCerca();

        Event::fake([UbicacionConductorActualizada::class]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/conductor/ubicacion', [
                'lat' => 10.4962,
                'lng' => -66.8518,
            ])
            ->assertOk();

        Event::assertNotDispatched(UbicacionConductorActualizada::class);
    }

    public function test_un_aviso_de_bandeja_emite_la_senal_de_notificacion(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $documento = UserDocument::factory()
            ->for($conductor)
            ->ofType(DocumentType::DriversLicense)
            ->approved()
            ->create(['expires_at' => now()->addDays(5)->toDateString()]);

        Event::fake([NotificacionCreada::class]);

        $conductor->notify(new DocumentoPorVencer($documento, 5));

        Event::assertDispatched(
            NotificacionCreada::class,
            fn (NotificacionCreada $event): bool => $event->usuario->id === $conductor->id
                && $event->notificacionId !== null
                && $event->broadcastOn()->name === 'private-App.Models.User.'.$conductor->id,
        );
    }
}
