<?php

namespace App\Domain\Trips;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Búsqueda y geocodificación inversa de direcciones.
 *
 * Usa Geoapify cuando hay clave (gratis, sin tarjeta). Si no, cae a
 * Nominatim, el servicio público de OpenStreetMap. En ambos casos devuelve
 * solo resultados de Venezuela y en español. Si los dos fallan, devuelve una
 * lista vacía: la app puede seguir usando coordenadas del mapa y direcciones
 * escritas a mano.
 */
class GeocodingService
{
    /** @return array<int, array{address: string, lat: float, lng: float}> */
    public function buscar(string $consulta): array
    {
        $resultados = $this->buscarGeoapify($consulta);

        if ($resultados !== []) {
            return $resultados;
        }

        return $this->buscarNominatim($consulta);
    }

    /** @return array{address: string, lat: float, lng: float}|null */
    public function reversa(float $lat, float $lng): ?array
    {
        return $this->reversaGeoapify($lat, $lng)
            ?? $this->reversaNominatim($lat, $lng);
    }

    public function disponible(): bool
    {
        return filled(config('services.geoapify.key'))
            || filled(config('services.nominatim.url'));
    }

    /**
     * @return array<int, array{address: string, lat: float, lng: float}>
     */
    private function buscarGeoapify(string $consulta): array
    {
        $clave = (string) config('services.geoapify.key');

        if ($clave === '') {
            return [];
        }

        $respuesta = $this->pedir('https://api.geoapify.com/v1/geocode/search', [
            'text' => $consulta,
            'filter' => 'countrycode:ve',
            'lang' => 'es',
            'limit' => 5,
            'apiKey' => $clave,
        ]);

        return $this->desdeGeoapify($respuesta);
    }

    /**
     * @return array{address: string, lat: float, lng: float}|null
     */
    private function reversaGeoapify(float $lat, float $lng): ?array
    {
        $clave = (string) config('services.geoapify.key');

        if ($clave === '') {
            return null;
        }

        $respuesta = $this->pedir('https://api.geoapify.com/v1/geocode/reverse', [
            'lat' => $lat,
            'lon' => $lng,
            'lang' => 'es',
            'limit' => 1,
            'apiKey' => $clave,
        ]);

        return $this->desdeGeoapify($respuesta)[0] ?? null;
    }

    /**
     * @return array<int, array{address: string, lat: float, lng: float}>
     */
    private function buscarNominatim(string $consulta): array
    {
        $base = rtrim((string) config('services.nominatim.url'), '/');

        if ($base === '') {
            return [];
        }

        $respuesta = $this->pedir("{$base}/search", [
            'q' => $consulta,
            'format' => 'jsonv2',
            'addressdetails' => 0,
            'countrycodes' => 've',
            'limit' => 5,
            'accept-language' => 'es',
        ], conAgente: true);

        $resultados = [];

        foreach ((array) $respuesta as $lugar) {
            if (! is_array($lugar) || ! isset($lugar['lat'], $lugar['lon'])) {
                continue;
            }

            $resultados[] = [
                'address' => (string) ($lugar['display_name'] ?? ''),
                'lat' => (float) $lugar['lat'],
                'lng' => (float) $lugar['lon'],
            ];
        }

        return array_slice($resultados, 0, 5);
    }

    /**
     * @return array{address: string, lat: float, lng: float}|null
     */
    private function reversaNominatim(float $lat, float $lng): ?array
    {
        $base = rtrim((string) config('services.nominatim.url'), '/');

        if ($base === '') {
            return null;
        }

        $respuesta = $this->pedir("{$base}/reverse", [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'jsonv2',
            'accept-language' => 'es',
        ], conAgente: true);

        if (! is_array($respuesta) || ! isset($respuesta['lat'], $respuesta['lon'])) {
            return null;
        }

        return [
            'address' => (string) ($respuesta['display_name'] ?? ''),
            'lat' => (float) $respuesta['lat'],
            'lng' => (float) $respuesta['lon'],
        ];
    }

    /**
     * @param  array<string, string|float|int>  $parametros
     * @return array<mixed>
     */
    private function pedir(string $url, array $parametros, bool $conAgente = false): array
    {
        try {
            $peticion = Http::timeout(8)->acceptJson();

            if ($conAgente) {
                $peticion = $peticion->withHeaders([
                    'User-Agent' => (string) config('services.nominatim.user_agent'),
                ]);
            }

            $respuesta = $peticion->get($url, $parametros);
        } catch (Throwable $error) {
            Log::warning('No se pudo consultar el servicio de direcciones.', [
                'url' => $url,
                'error' => $error->getMessage(),
            ]);

            return [];
        }

        if (! $respuesta->successful()) {
            Log::warning('El servicio de direcciones respondió con error.', [
                'url' => $url,
                'http' => $respuesta->status(),
            ]);

            return [];
        }

        $json = $respuesta->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<mixed>  $respuesta
     * @return array<int, array{address: string, lat: float, lng: float}>
     */
    private function desdeGeoapify(array $respuesta): array
    {
        $resultados = [];

        foreach ((array) ($respuesta['features'] ?? []) as $lugar) {
            if (! is_array($lugar)) {
                continue;
            }

            $propiedades = (array) ($lugar['properties'] ?? []);
            $lat = $propiedades['lat'] ?? null;
            $lng = $propiedades['lon'] ?? null;

            if ($lat === null || $lng === null) {
                $coordenadas = (array) ($lugar['geometry']['coordinates'] ?? []);

                if (count($coordenadas) < 2) {
                    continue;
                }

                [$lng, $lat] = $coordenadas;
            }

            $resultados[] = [
                'address' => (string) ($propiedades['formatted'] ?? ''),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        return array_slice($resultados, 0, 5);
    }
}
