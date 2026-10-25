<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaPanelTest extends TestCase
{
    use RefreshDatabase;

    private function auditor(): User
    {
        return User::factory()->auditor()->create();
    }

    private function actividad(User $autor, string $log = 'usuario', string $event = 'updated', string $description = 'Actualizó una cuenta'): Activity
    {
        return Activity::create([
            'log_name' => $log,
            'description' => $description,
            'event' => $event,
            'causer_type' => User::class,
            'causer_id' => $autor->getKey(),
            'properties' => [
                'old' => ['status' => 'pending'],
                'attributes' => ['status' => 'approved'],
            ],
        ]);
    }

    public function test_un_pasajero_no_entra_a_la_auditoria(): void
    {
        $pasajero = User::factory()->passenger()->approved()->create();

        $this->actingAs($pasajero)->get('/panel/auditoria')->assertForbidden();
    }

    public function test_el_auditor_ve_y_filtra_las_actividades(): void
    {
        $auditor = $this->auditor();
        $this->actividad($auditor, 'usuario', 'updated', 'Aprobó la cuenta de Ana');
        $this->actividad($auditor, 'recargas', 'created', 'Registró una recarga');

        $this->actingAs($auditor)
            ->get('/panel/auditoria')
            ->assertOk()
            ->assertSee('Aprobó la cuenta de Ana')
            ->assertSee('Registró una recarga')
            ->assertSee('Usuarios')
            ->assertSee('Recargas');

        $this->actingAs($auditor)
            ->get('/panel/auditoria?log=recargas')
            ->assertOk()
            ->assertSee('Registró una recarga')
            ->assertDontSee('Aprobó la cuenta de Ana');

        $this->actingAs($auditor)
            ->get('/panel/auditoria?evento=created')
            ->assertOk()
            ->assertSee('Registró una recarga')
            ->assertDontSee('Aprobó la cuenta de Ana');

        $this->actingAs($auditor)
            ->get('/panel/auditoria?q=Ana')
            ->assertOk()
            ->assertSee('Aprobó la cuenta de Ana')
            ->assertDontSee('Registró una recarga');
    }

    public function test_el_auditor_exporta_la_auditoria_en_csv(): void
    {
        $auditor = $this->auditor();
        $this->actividad($auditor, 'usuario', 'updated', 'Aprobó la cuenta de Ana');

        $respuesta = $this->actingAs($auditor)
            ->get('/panel/auditoria/exportar.csv?log=usuario');

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Fecha;Autor;Correo', $contenido);
        $this->assertStringContainsString('Aprobó la cuenta de Ana', $contenido);
        $this->assertStringContainsString($auditor->email, $contenido);
    }

    public function test_el_auditor_puede_filtrar_por_rango_de_fechas(): void
    {
        $auditor = $this->auditor();
        $this->actividad($auditor, 'usuario', 'updated', 'Actividad de hoy');

        $this->actingAs($auditor)
            ->get('/panel/auditoria?desde='.today()->toDateString().'&hasta='.today()->toDateString())
            ->assertOk()
            ->assertSee('Actividad de hoy');
    }
}
