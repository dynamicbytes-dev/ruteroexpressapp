<?php

namespace Tests\Feature\Trips;

use App\Enums\TripStatus;
use App\Models\DriverFavorite;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritosApiTest extends TestCase
{
    use RefreshDatabase;

    private function conductorConViaje(User $pasajero): User
    {
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();

        Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::Completed,
            'completed_at' => now(),
        ]);

        return $conductor;
    }

    public function test_el_pasajero_marca_y_quita_un_conductor_favorito(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $conductor = $this->conductorConViaje($pasajero);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/conductores-favoritos/{$conductor->id}")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('driver_favorites', [
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
        ]);

        // Marcar dos veces no duplica la fila.
        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/conductores-favoritos/{$conductor->id}")
            ->assertOk();

        $this->assertSame(1, DriverFavorite::query()->count());

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/conductores-favoritos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conductor->id)
            ->assertJsonPath('data.0.name', $conductor->name);

        $this->actingAs($pasajero, 'sanctum')
            ->deleteJson("/api/conductores-favoritos/{$conductor->id}")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);

        $this->assertSame(0, DriverFavorite::query()->count());
    }

    public function test_no_se_puede_marcar_a_un_conductor_sin_viaje_completado(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/conductores-favoritos/{$conductor->id}")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Solo puedes marcar como favorito a un conductor con el que ya completaste un viaje.',
            );
    }

    public function test_no_se_puede_marcar_a_un_pasajero_como_favorito(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/conductores-favoritos/{$otro->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ese usuario no es un conductor.');
    }

    public function test_un_conductor_no_administra_favoritos(): void
    {
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/conductores-favoritos')
            ->assertForbidden();
    }

    public function test_cada_pasajero_ve_solo_sus_favoritos(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $otro = User::factory()->passenger()->approved()->create();
        $conductor = $this->conductorConViaje($pasajero);

        DriverFavorite::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
        ]);

        $this->actingAs($otro, 'sanctum')
            ->getJson('/api/conductores-favoritos')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
