<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\RouteEstimator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proveedores de distancia/duración de la ruta.
 */
class RouteEstimatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.geoapify.key' => null,
            'services.google_maps.server_key' => null,
        ]);
    }

    public function test_usa_geoapify_cuando_hay_clave(): void
    {
        config(['services.geoapify.key' => 'clave-de-prueba']);

        Http::fake([
            'api.geoapify.com/v1/routing*' => Http::response([
                'features' => [[
                    'properties' => ['distance' => 3200, 'time' => 540],
                ]],
            ]),
        ]);

        $ruta = app(RouteEstimator::class)->estimar(10.5, -66.9, 10.51, -66.89);

        $this->assertSame('geoapify', $ruta['source']);
        $this->assertSame(3.2, $ruta['distance_km']);
        $this->assertSame(9, $ruta['duration_minutes']);
    }

    public function test_si_geoapify_falla_usa_la_estimacion_local(): void
    {
        config(['services.geoapify.key' => 'clave-de-prueba']);

        Http::fake([
            'api.geoapify.com/*' => Http::response(['error' => 'sin ruta'], 500),
        ]);

        $ruta = app(RouteEstimator::class)->estimar(10.5, -66.9, 10.51, -66.89);

        $this->assertSame('estimada', $ruta['source']);
        $this->assertGreaterThan(0, $ruta['distance_km']);
        $this->assertGreaterThanOrEqual(1, $ruta['duration_minutes']);
    }

    public function test_sin_claves_estima_localmente_sin_llamar_a_la_red(): void
    {
        $ruta = app(RouteEstimator::class)->estimar(10.5, -66.9, 10.51, -66.89);

        $this->assertSame('estimada', $ruta['source']);
        $this->assertGreaterThan(0, $ruta['distance_km']);
    }
}
