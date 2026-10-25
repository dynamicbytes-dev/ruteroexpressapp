<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Bandeja de avisos de la app.
 *
 * En F3 los avisos se guardan en base de datos (por ejemplo, documentos por
 * vencer). En F4 el mismo registro disparará además la notificación push.
 */
class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'data' => $usuario->notifications()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (DatabaseNotification $notificacion): array => $this->formatear($notificacion))
                ->all(),
            'unread_count' => $usuario->unreadNotifications()->count(),
        ]);
    }

    public function leer(Request $request, string $notification): JsonResponse
    {
        $aviso = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $aviso->markAsRead();

        return response()->json([
            'message' => 'Aviso marcado como leído.',
            'data' => $this->formatear($aviso->fresh()),
        ]);
    }

    public function leerTodas(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Todos los avisos quedaron como leídos.']);
    }

    /** @return array<string, mixed> */
    private function formatear(DatabaseNotification $notificacion): array
    {
        $datos = is_array($notificacion->data) ? $notificacion->data : [];

        return [
            'id' => $notificacion->id,
            'type' => $datos['type'] ?? 'general',
            'title' => $datos['title'] ?? 'Aviso de GAZ',
            'message' => $datos['message'] ?? '',
            'data' => $datos,
            'read_at' => $notificacion->read_at?->toIso8601String(),
            'created_at' => $notificacion->created_at?->toIso8601String(),
        ];
    }
}
