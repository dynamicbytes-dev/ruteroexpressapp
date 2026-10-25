<?php

namespace Tests\Feature\Trips;

use App\Enums\TripStatus;
use App\Models\DriverFavorite;
use App\Models\DriverProfile;
use App\Models\Trip;
use App\Models\TripRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalificacionesApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Trip} */
    private function viajeCompletado(): array
    {
        $pasajero = User::factory()->passenger()->approved()->create();
        $conductor = User::factory()->driver()->approved()->create();
        DriverProfile::factory()->for($conductor)->create();

        $viaje = Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::Completed,
            'completed_at' => now(),
        ]);

        return [$pasajero, $conductor, $viaje];
    }

    public function test_el_pasajero_califica_el_viaje_y_se_actualiza_el_promedio(): void
    {
        [$pasajero, $conductor, $viaje] = $this->viajeCompletado();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", [
                'rating' => 5,
                'comment' => 'Muy amable y puntual.',
                'favorite' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.rating.rating', 5)
            ->assertJsonPath('data.trip.rating.comment', 'Muy amable y puntual.')
            ->assertJsonPath('data.trip.can_rate', false)
            ->assertJsonPath('data.trip.driver.is_favorite', true);

        $this->assertDatabaseHas('trip_ratings', [
            'trip_id' => $viaje->id,
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'rating' => 5,
        ]);

        $perfil = $conductor->driverProfile->fresh();
        $this->assertSame(1, $perfil->ratings_count);
        $this->assertSame(5.0, (float) $perfil->rating_average);

        $this->assertDatabaseHas('driver_favorites', [
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
        ]);
    }

    public function test_el_promedio_se_recalcula_con_varias_calificaciones(): void
    {
        [$pasajero, $conductor, $viaje] = $this->viajeCompletado();

        TripRating::factory()->rating(3)->forTrip($viaje)->create();

        $otroViaje = Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$otroViaje->id}/calificar", ['rating' => 5])
            ->assertOk();

        $perfil = $conductor->driverProfile->fresh();
        $this->assertSame(2, $perfil->ratings_count);
        $this->assertSame(4.0, (float) $perfil->rating_average);
    }

    public function test_no_se_puede_calificar_un_viaje_dos_veces(): void
    {
        [$pasajero, , $viaje] = $this->viajeCompletado();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 4])
            ->assertOk();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya calificaste este viaje.');
    }

    public function test_no_se_puede_calificar_un_viaje_sin_completar(): void
    {
        [$pasajero, $conductor] = $this->viajeCompletado();

        $viaje = Trip::factory()->create([
            'passenger_id' => $pasajero->id,
            'driver_id' => $conductor->id,
            'status' => TripStatus::InProgress,
        ]);

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 5])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo puedes calificar un viaje completado.');
    }

    public function test_otro_pasajero_no_puede_calificar_el_viaje(): void
    {
        [, , $viaje] = $this->viajeCompletado();
        $intruso = User::factory()->passenger()->approved()->create();

        $this->actingAs($intruso, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo el pasajero del viaje puede calificarlo.');
    }

    public function test_la_calificacion_debe_estar_entre_uno_y_cinco(): void
    {
        [$pasajero, , $viaje] = $this->viajeCompletado();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", ['rating' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_el_conductor_ve_sus_calificaciones_sin_datos_del_pasajero(): void
    {
        [$pasajero, $conductor, $viaje] = $this->viajeCompletado();

        TripRating::factory()->rating(5)->forTrip($viaje)->create([
            'comment' => 'Excelente conducción.',
        ]);

        $respuesta = $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/conductor/calificaciones')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.comment', 'Excelente conducción.');

        $this->assertArrayNotHasKey('passenger', $respuesta->json('data.0'));
        $this->assertSame('passenger', User::find($pasajero->id)->role->value);
    }

    public function test_un_pasajero_no_ve_el_listado_de_calificaciones_del_conductor(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero, 'sanctum')
            ->getJson('/api/conductor/calificaciones')
            ->assertForbidden();
    }

    public function test_la_calificacion_incluye_promedio_y_favorito_en_el_viaje(): void
    {
        [$pasajero, $conductor, $viaje] = $this->viajeCompletado();

        $this->actingAs($pasajero, 'sanctum')
            ->postJson("/api/viajes/{$viaje->id}/calificar", [
                'rating' => 4,
                'favorite' => false,
            ])
            ->assertOk();

        $this->actingAs($pasajero, 'sanctum')
            ->getJson("/api/viajes/{$viaje->id}")
            ->assertOk()
            ->assertJsonPath('data.trip.rating.rating', 4)
            ->assertJsonPath('data.trip.driver.is_favorite', false)
            ->assertJsonPath('data.trip.driver.rating', 4);

        $this->assertSame(0, DriverFavorite::query()->count());
        $this->assertSame($conductor->id, $viaje->driver_id);
    }
}
