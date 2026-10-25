<?php

namespace Tests\Feature\Panel;

use App\Enums\TripOfferStatus;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViajesPanelTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    public function test_un_pasajero_no_entra_al_listado_de_viajes(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/viajes')->assertForbidden();
    }

    public function test_el_auditor_ve_los_viajes_activos_y_filtra_por_estado(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
        ]);
        $conductor = User::factory()->driver()->approved()->create();

        $activo = Trip::factory()
            ->for($pasajero, 'passenger')
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::InProgress,
                'origin_address' => 'Av. Libertador, Caracas',
                'destination_address' => 'Altamira, Caracas',
            ]);

        $completado = Trip::factory()
            ->for($pasajero, 'passenger')
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::Completed,
                'origin_address' => 'Plaza Venezuela',
                'destination_address' => 'Bellas Artes',
                'completed_at' => now(),
            ]);

        $this->actingAs($auditor)
            ->get('/panel/viajes')
            ->assertOk()
            ->assertSee('Viajes')
            ->assertSee('Av. Libertador, Caracas')
            ->assertDontSee('Plaza Venezuela');

        $this->actingAs($auditor)
            ->get('/panel/viajes?tab=completed')
            ->assertOk()
            ->assertSee('Plaza Venezuela')
            ->assertDontSee('Av. Libertador, Caracas');

        $this->actingAs($auditor)
            ->get('/panel/viajes?tab=all&buscar=Ana')
            ->assertOk()
            ->assertSee('Av. Libertador, Caracas')
            ->assertSee('Plaza Venezuela');

        $this->actingAs($auditor)
            ->get('/panel/viajes?tab=all&buscar='.$activo->id)
            ->assertOk()
            ->assertSee('Av. Libertador, Caracas');
    }

    public function test_la_ficha_del_viaje_muestra_personas_tarifa_y_alertas(): void
    {
        $auditor = $this->auditor();
        $pasajero = User::factory()->passenger()->approved()->create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
        ]);
        $conductor = User::factory()->driver()->approved()->create([
            'first_name' => 'Pedro',
            'last_name' => 'Gómez',
        ]);

        $viaje = Trip::factory()
            ->for($pasajero, 'passenger')
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::Accepted,
                'total' => 5.00,
                'commission_amount' => 0.50,
                'driver_earning' => 4.50,
            ]);

        TripOffer::factory()
            ->for($viaje)
            ->for($conductor, 'driver')
            ->create([
                'status' => TripOfferStatus::Accepted,
                'responded_at' => now(),
            ]);

        $this->actingAs($auditor)
            ->get("/panel/viajes/{$viaje->id}")
            ->assertOk()
            ->assertSee('Viaje #'.$viaje->id)
            ->assertSee('Ana Pérez')
            ->assertSee('Pedro Gómez')
            ->assertSee('$5.00')
            ->assertSee('$4.50')
            ->assertSee('Aceptada')
            ->assertSee('Recogida')
            ->assertSee('Destino');
    }

    public function test_el_dashboard_muestra_los_viajes_en_curso_y_enlaza_al_listado(): void
    {
        $auditor = $this->auditor();

        Trip::factory()->count(2)->create(['status' => TripStatus::Searching]);
        Trip::factory()->create(['status' => TripStatus::Completed, 'completed_at' => now()]);

        $this->actingAs($auditor)
            ->get('/panel')
            ->assertOk()
            ->assertSee('Viajes en curso')
            ->assertSee('2')
            ->assertSee(route('panel.viajes.index'));
    }
}
