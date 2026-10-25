<?php

namespace Tests\Feature\Trips;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Filtros rápidos del historial de viajes.
 *
 * La semana empieza el lunes y todos los límites se calculan en la zona
 * horaria de la aplicación (America/Caracas).
 */
class HistorialViajesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_el_conductor_filtra_su_historial_por_dia_semana_y_mes(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $hoy = $this->viaje($conductor, now()->startOfDay()->addHours(9));
        $estaSemana = $this->viaje(
            $conductor,
            now()->startOfWeek(Carbon::MONDAY)->addHours(9),
        );
        $esteMes = $this->viaje(
            $conductor,
            now()->startOfMonth()->addHours(9),
        );
        $mesAnterior = $this->viaje(
            $conductor,
            now()->startOfMonth()->subDay()->addHours(9),
        );

        $otroConductor = User::factory()->driver()->approved()->create();
        $viajeAjeno = $this->viaje(
            $otroConductor,
            now()->startOfDay()->addHours(10),
        );

        $this->actingAs($conductor, 'sanctum');

        $this->assertEqualsCanonicalizing(
            [$hoy->id],
            $this->ids($this->getJson('/api/viajes?period=day')),
        );

        $this->assertEqualsCanonicalizing(
            [$hoy->id, $estaSemana->id],
            $this->ids($this->getJson('/api/viajes?period=week')),
        );

        $this->assertEqualsCanonicalizing(
            [$hoy->id, $estaSemana->id, $esteMes->id],
            $this->ids($this->getJson('/api/viajes?period=month')),
        );

        $todos = $this->ids($this->getJson('/api/viajes'));

        $this->assertContains($hoy->id, $todos);
        $this->assertContains($mesAnterior->id, $todos);
        $this->assertNotContains($viajeAjeno->id, $todos, 'No se mezclan los viajes de otro conductor.');
    }

    public function test_el_filtro_se_conserva_en_las_paginas_adicionales(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        for ($indice = 0; $indice < 21; $indice++) {
            $this->viaje($conductor, now()->startOfDay()->addMinutes($indice));
        }

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/viajes?period=day&page=2')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data');
    }

    public function test_el_periodo_invalido_se_rechaza(): void
    {
        $conductor = User::factory()->driver()->approved()->create();

        $this->actingAs($conductor, 'sanctum')
            ->getJson('/api/viajes?period=semestre')
            ->assertStatus(422)
            ->assertJsonValidationErrors('period');
    }

    private function viaje(User $conductor, Carbon $solicitado): Trip
    {
        return Trip::factory()
            ->assignedTo($conductor)
            ->create([
                'status' => TripStatus::Completed,
                'requested_at' => $solicitado,
                'completed_at' => $solicitado->copy()->addHour(),
            ]);
    }

    /** @return list<int> */
    private function ids(TestResponse $respuesta): array
    {
        return collect($respuesta->json('data'))
            ->pluck('id')
            ->all();
    }
}
