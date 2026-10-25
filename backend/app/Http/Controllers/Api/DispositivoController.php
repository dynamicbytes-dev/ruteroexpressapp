<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de equipos para las notificaciones push.
 *
 * La app obtiene el token de Firebase al iniciar sesión y lo envía aquí. Al
 * cerrar sesión lo elimina antes de revocar el token de Sanctum. Si el mismo
 * equipo entra con otra cuenta, el token se reasigna a la cuenta nueva.
 */
class DispositivoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $equipo = DeviceToken::query()->updateOrCreate(
            ['token' => $datos['token']],
            [
                'user_id' => $request->user()->getKey(),
                'platform' => $datos['platform'],
                'device_name' => $datos['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Equipo registrado para notificaciones.',
            'data' => [
                'id' => $equipo->getKey(),
                'platform' => $equipo->platform,
            ],
        ], $equipo->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->getKey())
            ->where('token', $datos['token'])
            ->delete();

        return response()->json([
            'message' => 'Equipo eliminado de las notificaciones.',
        ]);
    }
}
