<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use App\Enums\TripOfferStatus;
use App\Enums\TripStatus;
use App\Enums\WalletTransactionType;
use App\Models\DriverProfile;
use App\Models\ExchangeRate;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViajesApiTest extends TestCase
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
    private function datosViaje(string $metodo = 'wallet'): array
    {
        return [
            'origin_lat' => 10.4961,
            'origin_lng' => -66.8517,
            'destination_lat' => 10.5003,
            'destination_lng' => -66.8428,
            'origin_address' => 'Av. Francisco de Miranda',
            'destination_address' => 'Plaza Venezuela',
            'payment_method' => $metodo,
        ];
    }

    public function test_cotiza_un_viaje_con_la_tarifa_y_la_tasa_vigente(): void
    {
        $pasajero = $this->pasajeroConSaldo();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes/cotizar', $this->datosViaje())
            ->assertOk()
            ->assertJsonPath('data.total', 3.35)
            ->assertJsonPath('data.driver_earning', 3.01)
            ->assertJsonPath('data.route_source', 'fake')
            ->assertJsonPath('data.local_total', 134);
    }

    public function test_el_pasajero_pide_un_viaje_con_billetera_y_recibe_una_alerta_el_conductor(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated()
            ->assertJsonPath('data.trip.status', 'offered')
            ->assertJsonPath('data.trip.driver', null);

        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->assertSame($conductor->id, $oferta->driver_id);
        $this->assertSame(3.35, (float) $viaje->total);
        // Primer viaje del pasajero: gratis, no hay retención de billetera.
        $this->assertSame(0.0, (float) $viaje->wallet_hold_amount);
        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
        $this->assertSame(100.0, (float) $pasajero->wallet->fresh()->balance);
        $this->assertSame('first_trips', $viaje->promo_type);
        $this->assertSame(3.35, (float) $viaje->discount_amount);
        $this->assertSame(3.35, (float) $viaje->subsidy_amount);
        $this->assertSame(TripOfferStatus::Pending, $oferta->status);

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/conductor/ofertas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trip.total', 3.35)
            ->assertJsonPath('data.0.trip.driver_earning', 3.01)
            ->assertJsonPath('data.0.trip.passenger_name', $pasajero->name);
    }

    public function test_el_conductor_acepta_y_el_viaje_se_liquida_en_la_billetera(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje())->assertCreated();
        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/ofertas/{$oferta->id}/aceptar")
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'accepted')
            ->assertJsonPath('data.trip.driver.id', $conductor->id)
            ->assertJsonPath('data.trip.passenger.id', $pasajero->id);

        foreach (['llegada' => 'arriving', 'esperando' => 'waiting', 'iniciar' => 'in_progress'] as $accion => $estado) {
            $this->actingAs($conductor, 'sanctum')
                ->postJson("/api/viajes/{$viaje->id}/{$accion}")
                ->assertOk()
                ->assertJsonPath('data.trip.status', $estado);
        }

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/finalizar")
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'completed');

        // Primer viaje del pasajero: gratis, saldo intacto.
        $this->assertSame(100.0, (float) $pasajero->wallet->fresh()->balance);
        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
        // Conductor recibe su ganancia completa (3.01) más el subsidio del descuento (3.35).
        $this->assertSame(6.36, (float) $conductor->wallet->fresh()->balance);
        $this->assertSame(1, $conductor->driverProfile->fresh()->trips_completed);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::TripEarning->value,
            'amount' => 3.01,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::Subsidy->value,
            'amount' => 3.35,
        ]);
        // No hay cargo al pasajero porque el viaje es gratis.
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $pasajero->wallet->id,
            'type' => WalletTransactionType::TripCharge->value,
        ]);
    }

    public function test_la_cascada_pasa_al_siguiente_y_bloquea_al_tercer_rechazo(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$primero] = $this->conductorCerca(-66.8517000);
        [$segundo] = $this->conductorCerca(-66.8516000);
        [$tercero] = $this->conductorCerca(-66.8515000);

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje())->assertCreated();

        $conductores = [$primero, $segundo, $tercero];

        foreach ($conductores as $indice => $conductor) {
            $oferta = TripOffer::query()->where('status', TripOfferStatus::Pending)->firstOrFail();

            $this->assertSame($conductor->id, $oferta->driver_id);

            $this->actingAs($conductor, 'sanctum')
                ->postJson("/api/ofertas/{$oferta->id}/rechazar")
                ->assertOk();

            $this->assertSame(1, $conductor->driverProfile->fresh()->trips_rejected);

            if ($indice < 2) {
                $this->assertSame(TripStatus::Offered, Trip::query()->firstOrFail()->status);
            }
        }

        $viaje = Trip::query()->firstOrFail();
        $terceroPerfil = $tercero->driverProfile->fresh();

        $this->assertSame(TripStatus::NoDriver, $viaje->status);
        $this->assertSame(0.0, (float) $viaje->wallet_hold_amount);
        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
        $this->assertFalse($terceroPerfil->isBlocked());
        $this->assertSame(1, (int) $terceroPerfil->trips_rejected);
        $this->assertSame(1, (int) $terceroPerfil->rejections_in_window);
    }

    public function test_tres_rechazos_del_mismo_conductor_en_la_ventana_lo_bloquean(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        for ($intento = 1; $intento <= 3; $intento++) {
            $this->actingAs($pasajero, 'sanctum')
                ->postJson('/api/viajes', $this->datosViaje())
                ->assertCreated();

            $oferta = TripOffer::query()
                ->where('status', TripOfferStatus::Pending)
                ->firstOrFail();

            $this->actingAs($conductor, 'sanctum')
                ->postJson("/api/ofertas/{$oferta->id}/rechazar")
                ->assertOk();

            $this->assertSame($intento, (int) $conductor->driverProfile->fresh()->trips_rejected);
        }

        $perfil = $conductor->driverProfile->fresh();

        $this->assertTrue($perfil->isBlocked());
        $this->assertFalse($perfil->is_online);
        $this->assertSame(0, (int) $perfil->rejections_in_window);
        $this->assertNotNull($perfil->blocked_reason);
    }

    public function test_sin_conductores_el_viaje_termina_sin_conductor_y_libera_el_saldo(): void
    {
        $pasajero = $this->pasajeroConSaldo();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje())
            ->assertCreated()
            ->assertJsonPath('data.trip.status', 'no_driver')
            ->assertJsonPath('message', 'No encontramos conductores cerca. Puedes intentarlo de nuevo.');

        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
        $this->assertSame(100.0, (float) $pasajero->wallet->fresh()->balance);
    }

    public function test_el_pago_en_efectivo_cobra_la_comision_y_puede_desconectar_por_deuda(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca(-66.8517, -5.10);

        // El conductor está por debajo del tope: no debe recibir alertas.
        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje('cash'))
            ->assertCreated()
            ->assertJsonPath('data.trip.status', 'no_driver');

        Trip::query()->delete();
        TripOffer::query()->delete();
        $conductor->wallet->forceFill(['balance' => -4.90])->save();

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje('cash'))->assertCreated();
        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($conductor, 'sanctum')->postJson("/api/ofertas/{$oferta->id}/aceptar")->assertOk();
        $this->actingAs($conductor, 'sanctum')->postJson("/api/viajes/{$viaje->id}/llegada")->assertOk();
        $this->actingAs($conductor, 'sanctum')->postJson("/api/viajes/{$viaje->id}/esperando")->assertOk();
        $this->actingAs($conductor, 'sanctum')->postJson("/api/viajes/{$viaje->id}/iniciar")->assertOk();
        $this->actingAs($conductor, 'sanctum')->postJson("/api/viajes/{$viaje->id}/finalizar")->assertOk();

        // Segundo viaje del pasajero: también gratis (primeros 2 viajes).
        // Conductor: comisión -0.34 + subsidio +3.35 = saldo -1.89.
        $this->assertSame(-1.89, (float) $conductor->wallet->fresh()->balance);
        // No llega al tope negativo (-5.00), así que no se desconecta.
        $this->assertTrue($conductor->driverProfile->fresh()->is_online);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::Commission->value,
            'amount' => 0.34,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::Subsidy->value,
            'amount' => 3.35,
        ]);
    }

    public function test_el_pasajero_cancela_y_se_libera_la_retencion(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje())->assertCreated();
        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/cancelar", ['reason' => 'Cambié de planes'])
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'cancelled')
            ->assertJsonPath('data.trip.cancelled_by', 'passenger');

        $this->assertSame(TripOfferStatus::Cancelled, $oferta->fresh()->status);
        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
        $this->assertSame(100.0, (float) $pasajero->wallet->fresh()->balance);
    }

    public function test_si_el_conductor_cancela_el_viaje_vuelve_a_busqueda_y_cuenta_como_rechazo(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje())->assertCreated();
        $viaje = Trip::query()->firstOrFail();
        $oferta = TripOffer::query()->firstOrFail();

        $this->actingAs($conductor, 'sanctum')->postJson("/api/ofertas/{$oferta->id}/aceptar")->assertOk();

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/cancelar", ['reason' => 'El pasajero no llegó'])
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'no_driver');

        $this->assertSame(1, $conductor->driverProfile->fresh()->trips_rejected);
        $this->assertSame(0.0, (float) $pasajero->wallet->fresh()->held_balance);
    }

    public function test_el_conductor_debe_reportar_ubicacion_antes_de_conectarse(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();
        Wallet::factory()->for($conductor)->create();

        $this->actingAs($conductor, 'sanctum')
            ->putJson('/api/conductor/disponibilidad', ['online' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Primero comparte tu ubicación para poder conectarte.');

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/conductor/ubicacion', ['lat' => 10.4961, 'lng' => -66.8517])
            ->assertOk();

        $this->actingAs($conductor, 'sanctum')
            ->putJson('/api/conductor/disponibilidad', ['online' => true])
            ->assertOk()
            ->assertJsonPath('data.online', true)
            ->assertJsonPath('data.available', true);
    }

    public function test_la_alerta_vencida_penaliza_y_sigue_la_cascada(): void
    {
        $pasajero = $this->pasajeroConSaldo();
        [$conductor] = $this->conductorCerca();

        $this->actingAs($pasajero, 'sanctum')->postJson('/api/viajes', $this->datosViaje())->assertCreated();
        $oferta = TripOffer::query()->firstOrFail();
        $oferta->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/conductor/ofertas')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(TripOfferStatus::Expired, $oferta->fresh()->status);
        $this->assertSame(TripStatus::NoDriver, Trip::query()->firstOrFail()->status);
        $this->assertSame(1, $conductor->driverProfile->fresh()->trips_rejected);
    }

    public function test_un_pasajero_en_espera_no_puede_pedir_viajes(): void
    {
        $pasajero = User::factory()->passenger()->create();
        Wallet::factory()->for($pasajero)->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje('cash'))
            ->assertForbidden();
    }

    public function test_un_conductor_no_puede_pedir_viajes_como_pasajero(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $this->actingAs($conductor, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje('cash'))
            ->assertForbidden();
    }

    public function test_finalizar_viaje_con_pago_de_billetera_descuenta_al_pasajero_y_acredita_al_conductor(): void
    {
        $pasajero = $this->pasajeroConSaldo(50.0);
        [$conductor] = $this->conductorCerca();

        $viaje = Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::InProgress,
            'payment_method' => \App\Enums\PaymentMethod::Wallet,
            'total' => 10.00,
            'discount_amount' => 2.00,
            'subsidy_amount' => 2.00,
            'driver_earning' => 8.50,
            'wallet_hold_amount' => 8.00,
            'origin_address' => 'Punto A',
            'destination_address' => 'Punto B',
        ]);

        $pasajero->wallet->forceFill(['held_balance' => 8.00])->save();

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/finalizar")
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'completed');

        // Pasajero: pagó 8.00 (10.00 - 2.00 descuento). Saldo: 50 - 8 = 42.00, held_balance: 0.
        $this->assertSame(42.00, (float) $pasajero->wallet->fresh()->balance);
        $this->assertSame(0.00, (float) $pasajero->wallet->fresh()->held_balance);

        // Conductor: recibe 8.50 (ganancia) + 2.00 (subsidio) = 10.50.
        $this->assertSame(10.50, (float) $conductor->wallet->fresh()->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $pasajero->wallet->id,
            'type' => WalletTransactionType::TripCharge->value,
            'direction' => 'debit',
            'amount' => 8.00,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::TripEarning->value,
            'direction' => 'credit',
            'amount' => 8.50,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::Subsidy->value,
            'direction' => 'credit',
            'amount' => 2.00,
        ]);
    }

    public function test_finalizar_viaje_falla_y_revierte_si_el_pasajero_no_tiene_saldo_suficiente(): void
    {
        $pasajero = $this->pasajeroConSaldo(5.00); // Saldo insuficiente (necesita 8.00)
        [$conductor] = $this->conductorCerca();

        $viaje = Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::InProgress,
            'payment_method' => \App\Enums\PaymentMethod::Wallet,
            'total' => 10.00,
            'discount_amount' => 2.00,
            'subsidy_amount' => 2.00,
            'driver_earning' => 8.50,
            'wallet_hold_amount' => 0.00,
            'origin_address' => 'Punto A',
            'destination_address' => 'Punto B',
        ]);

        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/finalizar")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Saldo insuficiente: necesitas $8.00 y tienes $5.00 disponibles.');

        // Saldos intactos debido al rollback atómico
        $this->assertSame(5.00, (float) $pasajero->wallet->fresh()->balance);
        $this->assertSame(0.00, (float) $conductor->wallet->fresh()->balance);

        // Estado del viaje no se completó
        $this->assertSame(TripStatus::InProgress, $viaje->fresh()->status);

        // Ninguna transacción registrada
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $pasajero->wallet->id,
            'type' => WalletTransactionType::TripCharge->value,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $conductor->wallet->id,
            'type' => WalletTransactionType::TripEarning->value,
        ]);

        // Probar también el endpoint explícito /pagar
        $this->actingAs($conductor, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/pagar")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Saldo insuficiente: necesitas $8.00 y tienes $5.00 disponibles.');
    }

    public function test_crear_viaje_falla_con_400_si_el_pasajero_no_tiene_saldo_disponible(): void
    {
        $pasajero = $this->pasajeroConSaldo(1.00); // Tarifa estimada es ~3.35, pasajero tiene 1.00

        // El pasajero ya tiene 2 viajes previos para que no aplique promoción gratis
        Trip::factory()->count(2)->create([
            'passenger_id' => $pasajero->id,
            'status' => TripStatus::Completed,
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson('/api/viajes', $this->datosViaje('wallet'))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Saldo insuficiente: necesitas $3.35 y tienes $1.00 disponibles.');

        // No se creó ningún viaje activo
        $this->assertDatabaseMissing('trips', [
            'passenger_id' => $pasajero->id,
            'status' => TripStatus::Searching->value,
        ]);
    }
}
