<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function conductorDisponible(array $perfil = []): User
    {
        $conductor = User::factory()->driver()->approved()->create();

        DriverProfile::factory()->for($conductor)->online()->create(array_merge([
            'last_location_at' => now(),
        ], $perfil));

        return $conductor->fresh();
    }

    public function test_el_conductor_activo_con_ubicacion_reciente_esta_disponible(): void
    {
        $this->assertTrue($this->conductorDisponible()->driverProfile->isAvailable());
    }

    public function test_un_conductor_inactivo_no_recibe_alertas(): void
    {
        $conductor = $this->conductorDisponible();
        $conductor->driverProfile->update(['is_online' => false]);

        $this->assertFalse($conductor->driverProfile->fresh()->isAvailable());
    }

    public function test_un_conductor_con_la_ubicacion_vieja_se_considera_desconectado(): void
    {
        $conductor = $this->conductorDisponible(['last_location_at' => now()->subMinutes(10)]);

        $this->assertFalse($conductor->driverProfile->isAvailable());
    }

    public function test_un_conductor_bloqueado_no_recibe_alertas(): void
    {
        $conductor = $this->conductorDisponible();
        $conductor->driverProfile->forceFill([
            'blocked_until' => now()->addHour(),
            'blocked_reason' => 'Excedió el número de rechazos permitidos.',
        ])->save();

        $perfil = $conductor->driverProfile->fresh();
        $this->assertTrue($perfil->isBlocked());
        $this->assertFalse($perfil->isAvailable());
    }

    public function test_un_conductor_con_cuenta_sin_aprobar_no_esta_disponible(): void
    {
        $conductor = User::factory()->driver()->create(); // queda en "pending"
        DriverProfile::factory()->for($conductor)->online()->create(['last_location_at' => now()]);

        $this->assertFalse($conductor->fresh()->driverProfile->isAvailable());
    }

    public function test_la_placa_se_guarda_normalizada(): void
    {
        $conductor = User::factory()->driver()->create();
        $perfil = DriverProfile::factory()->for($conductor)->create(['vehicle_plate' => 'ab-123- cd']);

        $this->assertSame('AB123CD', $perfil->fresh()->vehicle_plate);
    }

    public function test_el_bloqueo_por_rechazos_es_del_conductor_y_no_cambia_el_estado_de_la_cuenta(): void
    {
        // Son dos cosas distintas: el estado de la cuenta (users.status) lo
        // maneja el auditor, y el bloqueo por rechazos vive en el perfil del
        // conductor con su hora de reactivación.
        $conductor = $this->conductorDisponible();
        $conductor->driverProfile->forceFill(['blocked_until' => now()->addHour()])->save();
        $conductor->refresh();

        $this->assertSame(UserStatus::Approved, $conductor->status);
        $this->assertTrue($conductor->canOperate(), 'La cuenta sigue aprobada.');

        $this->assertFalse($conductor->driverProfile->isAvailable(), 'Pero no puede recibir viajes.');
        $this->assertTrue($conductor->driverProfile->isBlocked());
    }

    public function test_el_comando_desconecta_al_conductor_que_dejo_de_reportar(): void
    {
        config(['motoruta.drivers.offline_after_minutes' => 5]);

        $conductor = $this->conductorDisponible(['last_location_at' => now()->subMinutes(10)]);

        $this->artisan('conductores:desconectar-inactivos')->assertSuccessful();

        $this->assertFalse($conductor->driverProfile->fresh()->is_online);
        $this->assertNull($conductor->driverProfile->fresh()->went_online_at);
    }

    public function test_el_comando_no_desconecta_al_conductor_que_sigue_reportando(): void
    {
        config(['motoruta.drivers.offline_after_minutes' => 5]);

        $conductor = $this->conductorDisponible(['last_location_at' => now()->subMinute()]);

        $this->artisan('conductores:desconectar-inactivos')->assertSuccessful();

        $this->assertTrue($conductor->driverProfile->fresh()->is_online);
    }

    public function test_el_comando_desconecta_al_conductor_que_nunca_reporto_ubicacion(): void
    {
        config(['motoruta.drivers.offline_after_minutes' => 5]);

        $conductor = User::factory()->driver()->approved()->create();
        $perfil = DriverProfile::factory()->for($conductor)->online()->create([
            'last_location_at' => null,
        ]);

        $this->artisan('conductores:desconectar-inactivos')->assertSuccessful();

        $this->assertFalse($perfil->fresh()->is_online);
    }
}
