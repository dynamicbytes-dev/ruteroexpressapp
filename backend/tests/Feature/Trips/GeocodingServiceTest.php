<?php

namespace Tests\Feature\Trips;

use App\Domain\Trips\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Búsqueda y geocodificación inversa: Geoapify primero, Nominatim después.
 */
class GeocodingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['services.geoapify.key' => null]);
    }

    public function test_busca_con_geoapify_cuando_hay_clave(): void
    {
        config(['services.geoapify.key' => 'clave-de-prueba']);

        Http::fake([
            'api.geoapify.com/v1/geocode/search*' => Http::response([
                'features' => [[
                    'properties' => [
                        'formatted' => 'Av. Principal, Caracas, Venezuela',
                        'lat' => 10.5,
                        'lon' => -66.9,
                    ],
                ]],
            ]),
        ]);

        $resultados = app(GeocodingService::class)->buscar('Av. Principal');

        $this->assertCount(1, $resultados);
        $this->assertSame('Av. Principal, Caracas, Venezuela', $resultados[0]['address']);
        $this->assertSame(10.5, $resultados[0]['lat']);
        $this->assertSame(-66.9, $resultados[0]['lng']);
    }

    public function test_sin_clave_usa_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'display_name' => 'Caracas, Distrito Capital, Venezuela',
                    'lat' => '10.4806',
                    'lon' => '-66.9036',
                ],
            ]),
        ]);

        $resultados = app(GeocodingService::class)->buscar('Caracas');

        $this->assertCount(1, $resultados);
        $this->assertSame('Caracas, Distrito Capital, Venezuela', $resultados[0]['address']);
        $this->assertSame(10.4806, $resultados[0]['lat']);
    }

    public function test_reversa_con_geoapify(): void
    {
        config(['services.geoapify.key' => 'clave-de-prueba']);

        Http::fake([
            'api.geoapify.com/v1/geocode/reverse*' => Http::response([
                'features' => [[
                    'properties' => [
                        'formatted' => 'Av. Libertador, Caracas',
                        'lat' => 10.5,
                        'lon' => -66.9,
                    ],
                ]],
            ]),
        ]);

        $lugar = app(GeocodingService::class)->reversa(10.5, -66.9);

        $this->assertNotNull($lugar);
        $this->assertSame('Av. Libertador, Caracas', $lugar['address']);
        $this->assertSame(10.5, $lugar['lat']);
    }

    public function test_sin_clave_la_reversa_usa_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Av. Bolívar, Caracas, Venezuela',
                'lat' => '10.5',
                'lon' => '-66.9',
            ]),
        ]);

        $lugar = app(GeocodingService::class)->reversa(10.5, -66.9);

        $this->assertNotNull($lugar);
        $this->assertSame('Av. Bolívar, Caracas, Venezuela', $lugar['address']);
    }
}
