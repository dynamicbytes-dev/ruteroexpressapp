<?php

namespace App\Domain\Trips;

use App\Enums\VehicleType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Distancia y duración de la ruta entre dos coordenadas.
 *
 * Orden de proveedores:
 *
 * 1. Geoapify (gratis, sin tarjeta) en modo `motorcycle`.
 * 2. Google Directions, solo si existe `GOOGLE_MAPS_SERVER_KEY`.
 * 3. Estimación local: distancia en línea recta con un factor de rodeo y una
 *    velocidad promedio.
 *
 * El contrato que recibe la app es el mismo en los tres casos. El campo
 * `source` indica de dónde salió (`geoapify`, `google` o `estimada`).
 */
class RouteEstimator
{
    /**
     * @return array{distance_km: float, duration_minutes: int, source: string}
     */
    public function estimar(
        float $origenLat,
        float $origenLng,
        float $destinoLat,
        float $destinoLng,
        VehicleType $tipo = VehicleType::Motorcycle,
        ?float $velocidadPromedio = null,
    ): array {
        $geoapify = $this->desdeGeoapify($origenLat, $origenLng, $destinoLat, $destinoLng, $tipo);

        if ($geoapify !== null) {
            return $geoapify;
        }

        $google = $this->desdeGoogle($origenLat, $origenLng, $destinoLat, $destinoLng);

        if ($google !== null) {
            return $google;
        }

        return $this->estimacionLocal(
            $origenLat,
            $origenLng,
            $destinoLat,
            $destinoLng,
            $tipo,
            $velocidadPromedio,
        );
    }

    public function usarGeoapify(): bool
    {
        return filled(config('services.geoapify.key'));
    }

    public function usarGoogle(): bool
    {
        return filled(config('services.google_maps.server_key'));
    }

    /**
     * @return array{distance_km: float, duration_minutes: int, source: string}|null
     */
    private function desdeGeoapify(
        float $origenLat,
        float $origenLng,
        float $destinoLat,
        float $destinoLng,
        VehicleType $tipo,
    ): ?array {
        $clave = (string) config('services.geoapify.key');

        if ($clave === '') {
            return null;
        }

        $modo = $tipo === VehicleType::Car
            ? 'drive'
            : (string) config('services.geoapify.routing_mode', 'motorcycle');

        try {
            $respuesta = Http::timeout(8)
                ->acceptJson()
                ->get('https://api.geoapify.com/v1/routing', [
                    'waypoints' => "{$origenLat},{$origenLng}|{$destinoLat},{$destinoLng}",
                    'mode' => $modo,
                    'lang' => 'es',
                    'apiKey' => $clave,
                ]);
        } catch (Throwable $error) {
            Log::warning('No se pudo consultar Geoapify Routing.', [
                'error' => $error->getMessage(),
            ]);

            return null;
        }

        if (! $respuesta->successful()) {
            Log::warning('Geoapify Routing respondió con error.', [
                'http' => $respuesta->status(),
            ]);

            return null;
        }

        $metros = (float) $respuesta->json('features.0.properties.distance', 0);
        $segundos = (float) $respuesta->json('features.0.properties.time', 0);

        if ($metros <= 0 || $segundos <= 0) {
            return null;
        }

        return [
            'distance_km' => round($metros / 1000, 2),
            'duration_minutes' => max(1, (int) ceil($segundos / 60)),
            'source' => 'geoapify',
        ];
    }

    /**
     * @return array{distance_km: float, duration_minutes: int, source: string}|null
     */
    private function desdeGoogle(
        float $origenLat,
        float $origenLng,
        float $destinoLat,
        float $destinoLng,
    ): ?array {
        $clave = (string) config('services.google_maps.server_key');

        if ($clave === '') {
            return null;
        }

        try {
            $respuesta = Http::timeout(8)
                ->acceptJson()
                ->get('https://maps.googleapis.com/maps/api/directions/json', [
                    'origin' => "{$origenLat},{$origenLng}",
                    'destination' => "{$destinoLat},{$destinoLng}",
                    'mode' => 'driving',
                    'language' => 'es',
                    'units' => 'metric',
                    'key' => $clave,
                ]);
        } catch (Throwable $error) {
            Log::warning('No se pudo consultar Google Directions.', [
                'error' => $error->getMessage(),
            ]);

            return null;
        }

        if (! $respuesta->successful() || $respuesta->json('status') !== 'OK') {
            Log::warning('Google Directions respondió sin una ruta utilizable.', [
                'http' => $respuesta->status(),
                'status' => $respuesta->json('status'),
            ]);

            return null;
        }

        $metros = (float) $respuesta->json('routes.0.legs.0.distance.value', 0);
        $segundos = (float) $respuesta->json('routes.0.legs.0.duration.value', 0);

        if ($metros <= 0 || $segundos <= 0) {
            return null;
        }

        return [
            'distance_km' => round($metros / 1000, 2),
            'duration_minutes' => max(1, (int) ceil($segundos / 60)),
            'source' => 'google',
        ];
    }

    /**
     * @return array{distance_km: float, duration_minutes: int, source: string}
     */
    private function estimacionLocal(
        float $origenLat,
        float $origenLng,
        float $destinoLat,
        float $destinoLng,
        VehicleType $tipo,
        ?float $velocidadPromedio = null,
    ): array {
        $lineaRecta = $this->haversineKm($origenLat, $origenLng, $destinoLat, $destinoLng);
        $factor = max(1.0, (float) config('motoruta.routing.road_factor', 1.25));

        $respaldo = $tipo === VehicleType::Car
            ? (float) config('motoruta.routing.average_speed_car_kmh', 30)
            : (float) config('motoruta.routing.average_speed_kmh', 25);

        $velocidad = max(5.0, $velocidadPromedio ?? $respaldo);

        $distancia = round($lineaRecta * $factor, 2);

        return [
            'distance_km' => $distancia,
            'duration_minutes' => max(1, (int) ceil(($distancia / $velocidad) * 60)),
            'source' => 'estimada',
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
