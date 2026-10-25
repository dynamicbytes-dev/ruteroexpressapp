<?php

namespace App\Http\Controllers\Api;

use App\Domain\Trips\DriverFavoriteService;
use App\Http\Controllers\Controller;
use App\Http\Resources\DriverFavoriteResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Conductores favoritos del pasajero. */
class FavoriteController extends Controller
{
    public function __construct(private readonly DriverFavoriteService $favoritos) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403, 'Solo los pasajeros tienen conductores favoritos.');

        return response()->json([
            'data' => DriverFavoriteResource::collection(
                $this->favoritos->conductores($request->user()),
            )->resolve($request),
        ]);
    }

    public function store(Request $request, User $driver): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403, 'Solo los pasajeros pueden marcar favoritos.');

        try {
            $this->favoritos->marcar($request->user(), $driver);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => "{$driver->name} quedó en tus conductores favoritos.",
            'data' => ['is_favorite' => true],
        ]);
    }

    public function destroy(Request $request, User $driver): JsonResponse
    {
        abort_unless($request->user()->isPassenger(), 403, 'Solo los pasajeros administran sus favoritos.');

        $this->favoritos->quitar($request->user(), $driver);

        return response()->json([
            'message' => "{$driver->name} ya no está en tus favoritos.",
            'data' => ['is_favorite' => false],
        ]);
    }
}
