<?php

namespace App\Http\Controllers\Api;

use App\Domain\Trips\DriverService;
use App\Domain\Trips\TripDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DriverAvailabilityRequest;
use App\Http\Requests\Api\DriverLocationRequest;
use App\Http\Resources\TripOfferResource;
use App\Http\Resources\TripResource;
use App\Models\TripOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Conexión, ubicación, alertas y viaje activo del conductor. */
class DriverController extends Controller
{
    public function __construct(
        private readonly DriverService $conductores,
        private readonly TripDispatcher $asignador,
    ) {}

    public function estado(Request $request): JsonResponse
    {
        try {
            $resumen = $this->conductores->resumen($request->user());
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json(['data' => $resumen]);
    }

    public function disponibilidad(DriverAvailabilityRequest $request): JsonResponse
    {
        try {
            $perfil = $this->conductores->actualizarDisponibilidad(
                $request->user(),
                $request->boolean('online'),
            );
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => $perfil->is_online
                ? 'Estás conectado. Te avisaremos cuando haya un viaje cerca.'
                : 'Estás desconectado. No recibirás alertas de viaje.',
            'data' => $this->conductores->resumen($request->user()),
        ]);
    }

    public function ubicacion(DriverLocationRequest $request): JsonResponse
    {
        try {
            $perfil = $this->conductores->actualizarUbicacion(
                $request->user(),
                (float) $request->validated('lat'),
                (float) $request->validated('lng'),
                $request->validated('bearing'),
                $request->validated('speed_kmh'),
            );
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'location' => $perfil->coordinates(),
                'updated_at' => $perfil->last_location_at?->toIso8601String(),
            ],
        ]);
    }

    public function ofertas(Request $request): JsonResponse
    {
        $ofertas = $this->conductores->ofertasPendientes($request->user());

        return TripOfferResource::collection($ofertas)->response();
    }

    public function aceptar(Request $request, TripOffer $offer): JsonResponse
    {
        try {
            $viaje = $this->asignador->aceptar($offer, $request->user());
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Viaje aceptado. Ve al punto de recogida.',
            'data' => ['trip' => TripResource::make($viaje->load(['passenger', 'driver.driverProfile', 'currentOffer', 'rating']))],
        ]);
    }

    public function rechazar(Request $request, TripOffer $offer): JsonResponse
    {
        try {
            $viaje = $this->asignador->rechazar($offer, $request->user());
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rechazaste el viaje. Buscaremos otro conductor.',
            'data' => ['trip_id' => $viaje->id],
        ]);
    }

    public function viajeActivo(Request $request): JsonResponse
    {
        try {
            $viaje = $this->conductores->viajeActivo($request->user());
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'data' => ['trip' => $viaje === null ? null : TripResource::make($viaje)],
        ]);
    }
}
