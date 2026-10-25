<?php

namespace App\Http\Controllers\Api;

use App\Domain\Trips\GeocodingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Búsqueda de direcciones para el mapa (Geoapify o OpenStreetMap). */
class GeocodingController extends Controller
{
    public function __construct(private readonly GeocodingService $geocoding) {}

    public function buscar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:160'],
        ], messages: [
            'q.required' => 'Escribe una dirección o un lugar.',
            'q.min' => 'Escribe al menos tres caracteres.',
        ]);

        return response()->json([
            'data' => [
                'available' => $this->geocoding->disponible(),
                'results' => $this->geocoding->buscar($datos['q']),
            ],
        ]);
    }

    public function reversa(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return response()->json([
            'data' => [
                'available' => $this->geocoding->disponible(),
                'result' => $this->geocoding->reversa((float) $datos['lat'], (float) $datos['lng']),
            ],
        ]);
    }
}
