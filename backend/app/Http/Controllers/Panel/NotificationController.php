<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bandeja de avisos operativos del auditor.
 *
 * Son notificaciones de base de datos: no salen por correo ni por push. El
 * auditor las ve en la campana del panel y puede abrir el registro relacionado.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filtro = $request->query('filtro') === 'todas' ? 'todas' : 'no_leidas';

        $notificaciones = $request->user()
            ->notifications()
            ->when($filtro === 'no_leidas', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('panel.notificaciones.index', [
            'notificaciones' => $notificaciones,
            'filtro' => $filtro,
            'noLeidas' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $aviso = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $aviso->markAsRead();

        $destino = (string) data_get($aviso->data, 'url', '');

        // Solo se permiten destinos internos: un dato manipulado no puede
        // convertir la campana en un redirect abierto.
        if ($destino === '' || ! str_starts_with($destino, url('/'))) {
            $destino = route('panel.notificaciones.index');
        }

        return redirect($destino);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Marcaste todas las notificaciones como leídas.');
    }
}
