<?php

namespace App\Http\Controllers\Panel;

use App\Enums\SupportConversationStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Módulo de soporte del panel.
 *
 * El auditor ve los chats abiertos, responde y puede darlos por terminados.
 * Al cerrar uno, la conversación desaparece de la app del usuario pero se
 * conserva completa en el historial del panel.
 */
class SupportController extends Controller
{
    public const TABS = [
        'abiertas' => 'Abiertas',
        'historial' => 'Historial',
        'todas' => 'Todas',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS)
            ? (string) $request->query('tab')
            : 'abiertas';

        $busqueda = trim((string) $request->query('buscar', ''));

        $conversaciones = SupportConversation::query()
            ->with(['user', 'latestMessage'])
            ->withCount([
                'messages as unread_count' => fn (Builder $query) => $query
                    ->where('sender_type', SupportMessage::AUTOR_USUARIO)
                    ->whereNull('read_at'),
            ])
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $digitos = preg_replace('/\D/', '', $busqueda) ?? '';

                $query->whereHas('user', function (Builder $sub) use ($busqueda, $digitos): void {
                    $sub->where('first_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('last_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('email', 'ilike', "%{$busqueda}%");

                    if (strlen($digitos) >= 5) {
                        $sub->orWhere('national_id', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->paginate(20)
            ->withQueryString();

        return view('panel.soporte.index', [
            'conversaciones' => $conversaciones,
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'contadores' => [
                'abiertas' => SupportConversation::query()->open()->count(),
                'historial' => SupportConversation::query()->closed()->count(),
                'todas' => SupportConversation::query()->count(),
            ],
        ]);
    }

    public function show(SupportConversation $conversation): View
    {
        $conversation->load([
            'user.city',
            'closedBy',
            'messages' => fn ($query) => $query->with('sender')->oldest('id'),
        ]);

        // Abrir la conversación marca como leídos los mensajes del usuario.
        $conversation->messages()
            ->where('sender_type', SupportMessage::AUTOR_USUARIO)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('panel.soporte.show', [
            'conversacion' => $conversation,
        ]);
    }

    public function responder(Request $request, SupportConversation $conversation): RedirectResponse
    {
        if (! $conversation->estaAbierta()) {
            return back()->with('error', 'Esta conversación ya está finalizada. Abre una nueva desde la app para continuar.');
        }

        $datos = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], messages: [
            'message.required' => 'Escribe una respuesta antes de enviarla.',
            'message.max' => 'La respuesta no puede pasar de 2000 caracteres.',
        ]);

        $conversation->messages()->create([
            'sender_id' => $request->user()->getKey(),
            'sender_type' => SupportMessage::AUTOR_AUDITOR,
            'body' => trim($datos['message']),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return redirect()
            ->route('panel.soporte.show', $conversation)
            ->with('status', 'Respuesta enviada.');
    }

    public function cerrar(Request $request, SupportConversation $conversation): RedirectResponse
    {
        if (! $conversation->estaAbierta()) {
            return redirect()
                ->route('panel.soporte.show', $conversation)
                ->with('error', 'Esta conversación ya estaba finalizada.');
        }

        $conversation->forceFill([
            'status' => SupportConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $request->user()->getKey(),
        ])->save();

        activity('soporte')
            ->causedBy($request->user())
            ->performedOn($conversation)
            ->withProperties([
                'usuario' => $conversation->user?->name,
                'mensajes' => $conversation->messages()->count(),
            ])
            ->log('Conversación de soporte finalizada');

        return redirect()
            ->route('panel.soporte.show', $conversation)
            ->with('status', 'Conversación finalizada. El chat se cerró en la app del usuario y queda en el historial.');
    }

    /**
     * Sondeo del chat abierto en el panel. Devuelve solo los datos visibles;
     * el HTML lo arma el JS con `textContent` para no inyectar contenido.
     */
    public function mensajes(SupportConversation $conversation): JsonResponse
    {
        $conversation->messages()
            ->where('sender_type', SupportMessage::AUTOR_USUARIO)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->load([
            'messages' => fn ($query) => $query->with('sender')->oldest('id'),
        ]);

        $mensajes = $conversation->messages->map(fn (SupportMessage $mensaje): array => [
            'id' => $mensaje->id,
            'body' => $mensaje->body,
            'from_user' => $mensaje->esDeUsuario(),
            'author' => $mensaje->esDeUsuario()
                ? ($mensaje->sender?->name ?? 'Usuario')
                : ($mensaje->sender?->name ?? 'Soporte'),
            'created_at' => $mensaje->created_at?->toIso8601String(),
            'created_at_label' => $this->fechaLegible($mensaje->created_at),
        ])->values()->all();

        return response()->json([
            'status' => $conversation->status->value,
            'status_label' => $conversation->status->label(),
            'signature' => implode(':', [
                count($mensajes),
                $mensajes === [] ? 0 : $mensajes[array_key_last($mensajes)]['id'],
            ]),
            'messages' => $mensajes,
        ]);
    }

    /** @param  Builder<SupportConversation>  $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'abiertas' => $query->open(),
            'historial' => $query->closed(),
            default => null,
        };
    }

    private function fechaLegible(?Carbon $fecha): string
    {
        if ($fecha === null) {
            return '';
        }

        return $fecha->isToday()
            ? $fecha->format('H:i')
            : $fecha->translatedFormat('d M, H:i');
    }
}
