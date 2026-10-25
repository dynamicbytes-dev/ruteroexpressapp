<?php

namespace App\Http\Controllers\Api;

use App\Domain\Trips\RatingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RateTripRequest;
use App\Http\Resources\TripRatingResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Calificaciones del pasajero al conductor. */
class RatingController extends Controller
{
    public function __construct(private readonly RatingService $calificaciones) {}

    public function store(RateTripRequest $request, Trip $trip): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403, 'Solo los pasajeros pueden calificar viajes.');

        try {
            $viaje = $this->calificaciones->calificar(
                $trip,
                $request->user(),
                $request->validated(),
            );
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Gracias por calificar tu viaje.',
            'data' => [
                'trip' => TripResource::make($viaje),
                'rating' => TripRatingResource::make($viaje->rating)->resolve($request),
            ],
        ]);
    }

    /** Calificaciones recibidas por el conductor autenticado. */
    public function conductor(Request $request): JsonResponse
    {
        abort_unless($request->user()->isDriver(), 403, 'Solo los conductores tienen calificaciones.');

        $recibidas = $this->calificaciones->recibidas($request->user());

        return response()->json([
            'data' => TripRatingResource::collection($recibidas)->resolve($request),
        ]);
    }
}
