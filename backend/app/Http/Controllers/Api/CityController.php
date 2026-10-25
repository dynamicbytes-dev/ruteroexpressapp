<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;

/**
 * Ciudades disponibles para el registro.
 *
 * Es público: la persona tiene que elegir su zona antes de tener una cuenta.
 * Solo se listan las ciudades activas, en el orden en que se muestran en la app.
 */
class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $ciudades = City::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (City $ciudad): array => [
                'id' => $ciudad->id,
                'name' => $ciudad->name,
                'state' => $ciudad->state,
                'display_name' => $ciudad->displayName(),
                'lat' => (float) $ciudad->lat,
                'lng' => (float) $ciudad->lng,
            ]);

        return response()->json(['data' => $ciudades]);
    }
}
