<?php

namespace Tests\Feature\Panel;

use App\Enums\DocumentType;
use App\Enums\TripStatus;
use App\Models\City;
use App\Models\DriverFavorite;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\TripRating;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VerificationReview;
use App\Models\Wallet;
use App\Notifications\CuentaInhabilitada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConductoresPanelTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_un_pasajero_no_entra_al_panel_de_conductores(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/conductores')->assertForbidden();
    }

    public function test_el_auditor_ve_y_filtra_conductores(): void
    {
        $auditor = $this->auditor();
        $enLinea = User::factory()->driver()->approved()->create([
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
        ]);
        DriverProfile::factory()->for($enLinea)->online()->create([
            'vehicle_plate' => 'AB123CD',
        ]);
        Wallet::factory()->for($enLinea)->saldo(12)->create();

        $desconectado = User::factory()->driver()->approved()->create([
            'first_name' => 'Luisa',
            'last_name' => 'Rojas',
        ]);
        DriverProfile::factory()->for($desconectado)->create([
            'vehicle_plate' => 'XY987ZZ',
        ]);

        $this->actingAs($auditor)
            ->get('/panel/conductores')
            ->assertOk()
            ->assertSee('Carlos Mendoza')
            ->assertSee('Luisa Rojas')
            ->assertSee('AB123CD');

        $this->actingAs($auditor)
            ->get('/panel/conductores?tab=en_linea')
            ->assertOk()
            ->assertSee('Carlos Mendoza')
            ->assertDontSee('Luisa Rojas');

        $this->actingAs($auditor)
            ->get('/panel/conductores?buscar=XY987ZZ')
            ->assertOk()
            ->assertSee('Luisa Rojas')
            ->assertDontSee('Carlos Mendoza');
    }

    public function test_el_auditor_ve_los_documentos_por_vencer_y_las_deudas(): void
    {
        $auditor = $this->auditor();

        $porVencer = User::factory()->driver()->approved()->create(['first_name' => 'PorVencer']);
        DriverProfile::factory()->for($porVencer)->create();
        UserDocument::factory()->for($porVencer)->ofType(DocumentType::DriversLicense)->create([
            'expires_at' => today()->addDays(10),
        ]);

        $conDeuda = User::factory()->driver()->approved()->create(['first_name' => 'ConDeuda']);
        DriverProfile::factory()->for($conDeuda)->create();
        Wallet::factory()->for($conDeuda)->saldo(-20)->create();

        $this->actingAs($auditor)
            ->get('/panel/conductores?tab=por_vencer')
            ->assertOk()
            ->assertSee('PorVencer')
            ->assertDontSee('ConDeuda');

        $this->actingAs($auditor)
            ->get('/panel/conductores?tab=deuda')
            ->assertOk()
            ->assertSee('ConDeuda')
            ->assertDontSee('PorVencer');
    }

    public function test_la_ficha_del_conductor_muestra_su_actividad(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Gil',
        ]);
        DriverProfile::factory()->for($conductor)->online()->create([
            'vehicle_plate' => 'AA111AA',
        ]);
        Wallet::factory()->for($conductor)->saldo(8.50)->create();
        UserDocument::factory()->for($conductor)->ofType(DocumentType::DriversLicense)->create([
            'expires_at' => today()->addDays(20),
        ]);

        VerificationReview::factory()->for($conductor)->create([
            'scope' => VerificationReview::SCOPE_ACCOUNT,
            'decision' => VerificationReview::DECISION_SUSPENDED,
            'reason' => 'Documento de identidad no legible.',
            'auditor_id' => $auditor->id,
        ]);

        Trip::factory()
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::Completed,
                'completed_at' => now(),
            ]);

        $this->actingAs($auditor)
            ->get("/panel/conductores/{$conductor->id}")
            ->assertOk()
            ->assertSee('Pedro Gil')
            ->assertSee('AA111AA')
            ->assertSee('Viajes completados')
            ->assertSee('Historial de verificación')
            ->assertSee('Suspendido');
    }

    public function test_la_ficha_solo_abre_para_conductores(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->get("/panel/conductores/{$pasajero->id}")
            ->assertNotFound();
    }

    public function test_el_auditor_cambia_la_zona_de_un_conductor_y_lo_desconecta(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create();
        Wallet::factory()->for($conductor)->saldo(4)->create();

        $maturin = City::query()->where('slug', 'maturin')->firstOrFail();

        $this->actingAs($auditor)
            ->from("/panel/conductores/{$conductor->id}")
            ->post("/panel/conductores/{$conductor->id}/zona", [
                'city_id' => $maturin->id,
            ])
            ->assertRedirect("/panel/conductores/{$conductor->id}")
            ->assertSessionHas('status');

        $perfil->refresh();

        $this->assertSame($maturin->id, $perfil->city_id);
        $this->assertFalse($perfil->is_online, 'Debe conectarse de nuevo en su nueva zona.');
    }

    public function test_solo_el_auditor_puede_cambiar_la_zona_de_un_conductor(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create();
        $maturin = City::query()->where('slug', 'maturin')->firstOrFail();

        $this->actingAs($conductor)
            ->post("/panel/conductores/{$conductor->id}/zona", [
                'city_id' => $maturin->id,
            ])
            ->assertForbidden();

        $this->assertNotSame($maturin->id, $perfil->fresh()->city_id);
    }

    public function test_el_auditor_inhabilita_y_habilita_a_un_conductor(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create([
            'first_name' => 'Nestor',
            'last_name' => 'Arias',
        ]);
        DriverProfile::factory()->for($conductor)->online()->create();
        Wallet::factory()->for($conductor)->saldo(4)->create();

        $this->actingAs($auditor)
            ->from("/panel/conductores/{$conductor->id}")
            ->post("/panel/conductores/{$conductor->id}/inhabilitar", [
                'reason' => 'Condujo de forma temeraria durante un viaje.',
            ])
            ->assertRedirect("/panel/conductores/{$conductor->id}")
            ->assertSessionHas('status');

        $conductor->refresh();

        $this->assertSame('suspended', $conductor->status->value);
        $this->assertSame('Condujo de forma temeraria durante un viaje.', $conductor->status_reason);
        $this->assertFalse($conductor->driverProfile->fresh()->is_online);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $conductor->id,
            'type' => CuentaInhabilitada::class,
        ]);

        $this->assertDatabaseHas('verification_reviews', [
            'user_id' => $conductor->id,
            'scope' => VerificationReview::SCOPE_ACCOUNT,
            'decision' => VerificationReview::DECISION_SUSPENDED,
        ]);

        $this->actingAs($auditor)
            ->get('/panel/conductores?tab=inhabilitados')
            ->assertOk()
            ->assertSee('Nestor Arias');

        $this->actingAs($auditor)
            ->from("/panel/conductores/{$conductor->id}")
            ->post("/panel/conductores/{$conductor->id}/habilitar")
            ->assertRedirect("/panel/conductores/{$conductor->id}")
            ->assertSessionHas('status');

        $this->assertSame('approved', $conductor->refresh()->status->value);
        $this->assertNull($conductor->status_reason);
    }

    public function test_inhabilitar_exige_un_motivo(): void
    {
        $auditor = $this->auditor();
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();

        $this->actingAs($auditor)
            ->post("/panel/conductores/{$conductor->id}/inhabilitar", [
                'reason' => 'corto',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('approved', $conductor->refresh()->status->value);
    }

    public function test_no_se_puede_inhabilitar_desde_la_ficha_de_un_pasajero(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($auditor)
            ->post("/panel/conductores/{$pasajero->id}/inhabilitar", [
                'reason' => 'Motivo suficientemente largo.',
            ])
            ->assertNotFound();
    }

    public function test_la_ficha_muestra_las_calificaciones_y_favoritos(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create([
            'first_name' => 'Ana',
            'last_name' => 'Perez',
        ]);
        $conductor = User::factory()->driver()->approved()->create([
            'first_name' => 'Luis',
            'last_name' => 'Mora',
        ]);
        DriverProfile::factory()->for($conductor)->create([
            'rating_average' => 5,
            'ratings_count' => 1,
        ]);

        $viaje = Trip::factory()->assignedTo($conductor)->create([
            'passenger_id' => $pasajero->id,
            'status' => TripStatus::Completed,
            'completed_at' => now(),
        ]);

        TripRating::factory()->forTrip($viaje)->rating(5)->create([
            'comment' => 'Muy atento y puntual.',
        ]);

        DriverFavorite::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
        ]);

        $this->actingAs($auditor)
            ->get("/panel/conductores/{$conductor->id}")
            ->assertOk()
            ->assertSee('Calificaciones recientes')
            ->assertSee('Muy atento y puntual.')
            ->assertSee('Ana Perez')
            ->assertSee('1 favorito')
            ->assertSee('★ 5.0');
    }
}
