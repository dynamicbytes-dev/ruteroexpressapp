<?php

namespace Tests\Feature\Panel;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerPanelTest extends TestCase
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

    public function test_el_auditor_ve_el_listado_de_pasajeros(): void
    {
        $pasajero = User::factory()->create([
            'role' => UserRole::Passenger,
            'status' => UserStatus::Approved,
            'first_name' => 'María',
            'last_name' => 'González',
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.pasajeros.index'));

        $response->assertOk()
            ->assertSee('Pasajeros')
            ->assertSee('María González')
            ->assertSee($pasajero->national_id);
    }

    public function test_el_auditor_ve_la_ficha_completa_del_pasajero(): void
    {
        $pasajero = User::factory()->create([
            'role' => UserRole::Passenger,
            'status' => UserStatus::Approved,
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
        ]);

        Wallet::create([
            'user_id' => $pasajero->id,
            'balance' => 45.50,
            'held_balance' => 0,
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.pasajeros.show', $pasajero));

        $response->assertOk()
            ->assertSee('Carlos Pérez')
            ->assertSee('45,50')
            ->assertSee('Ficha PDF');
    }

    public function test_el_auditor_edita_los_datos_del_pasajero(): void
    {
        $pasajero = User::factory()->create([
            'role' => UserRole::Passenger,
            'status' => UserStatus::Approved,
            'first_name' => 'Carlos',
            'last_name' => 'Pérez',
            'email' => 'carlos.original@gmail.com',
            'national_id' => '12345678',
        ]);

        $ciudad = City::create([
            'name' => 'Caracas',
            'slug' => 'caracas',
            'state' => 'Distrito Capital',
            'lat' => 10.4806,
            'lng' => -66.9036,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->auditor)
            ->patch(route('panel.pasajeros.update', $pasajero), [
                'first_name' => 'Carlos Alberto',
                'last_name' => 'Pérez Soto',
                'national_id' => '12345678',
                'email' => 'carlos.nuevo@gmail.com',
                'phone' => '04141234567',
                'city_id' => $ciudad->id,
                'status' => UserStatus::Approved->value,
            ]);

        $response->assertRedirect(route('panel.pasajeros.show', $pasajero));

        $pasajero->refresh();
        $this->assertSame('Carlos Alberto', $pasajero->first_name);
        $this->assertSame('Pérez Soto', $pasajero->last_name);
        $this->assertSame('carlos.nuevo@gmail.com', $pasajero->email);
        $this->assertSame('04141234567', $pasajero->phone);
    }

    public function test_el_auditor_inhabilita_y_habilita_a_un_pasajero(): void
    {
        $pasajero = User::factory()->create([
            'role' => UserRole::Passenger,
            'status' => UserStatus::Approved,
        ]);

        $responseInhabilitar = $this->actingAs($this->auditor)
            ->post(route('panel.pasajeros.inhabilitar', $pasajero), [
                'reason' => 'Incumplimiento reiterado de normas del servicio.',
            ]);

        $responseInhabilitar->assertSessionHasNoErrors();
        $pasajero->refresh();
        $this->assertTrue($pasajero->isSuspended());

        $responseHabilitar = $this->actingAs($this->auditor)
            ->post(route('panel.pasajeros.habilitar', $pasajero));

        $responseHabilitar->assertSessionHasNoErrors();
        $pasajero->refresh();
        $this->assertTrue($pasajero->isApproved());
    }

    public function test_el_auditor_exporta_la_ficha_del_pasajero_en_pdf(): void
    {
        $pasajero = User::factory()->create([
            'role' => UserRole::Passenger,
            'status' => UserStatus::Approved,
            'first_name' => 'Elena',
            'last_name' => 'Rivas',
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('panel.pasajeros.pdf', $pasajero));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
