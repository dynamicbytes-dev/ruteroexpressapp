<?php

namespace Tests\Feature\Panel;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los contadores del dashboard deben describir lo que dice su etiqueta.
 */
class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_cuentas_aprobadas_no_incluyen_al_auditor_y_los_rechazados_son_documentos(): void
    {
        $auditor = User::factory()->auditor()->create();
        $pasajero = User::factory()->passenger()->approved()->create();

        UserDocument::factory()->for($pasajero)->ofType(DocumentType::NationalId)->create([
            'status' => DocumentStatus::Rejected,
            'rejection_reason' => 'La foto está borrosa.',
        ]);

        $respuesta = $this->actingAs($auditor)->get('/panel')->assertOk();

        $summary = $respuesta->viewData('summary');

        $this->assertSame(1, $summary['aprobados']);
        $this->assertSame(1, $summary['rechazados']);
    }
}
