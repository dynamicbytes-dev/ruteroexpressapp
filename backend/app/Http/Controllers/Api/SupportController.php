<?php

namespace App\Http\Controllers\Api;

use App\Domain\Notifications\NotificarAuditores;
use App\Enums\SupportConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportMessageRequest;
use App\Http\Resources\SupportConversationResource;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Chat de soporte de pasajeros y conductores.
 *
 * Una persona solo ve su conversación abierta. Cuando el auditor la finaliza,
 * la app recibe `null` y el hilo desaparece; el historial queda en el panel.
 */
class SupportController extends Controller
{
    public function __construct(private readonly NotificarAuditores $notificar) {}

    public function show(Request $request): JsonResponse
    {
        $usuario = $this->usuarioDeLaApp($request);

        $conversacion = $this->conversacionAbierta($usuario);

        if ($conversacion === null) {
            return response()->json(['data' => null]);
        }

        // Al abrir el chat, los mensajes del equipo quedan como leídos.
        $conversacion->messages()
            ->where('sender_type', SupportMessage::AUTOR_AUDITOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => $this->formatear($conversacion, $request),
        ]);
    }

    public function store(SupportMessageRequest $request): JsonResponse
    {
        $usuario = $this->usuarioDeLaApp($request);
        $texto = trim($request->validated('message'));

        [$conversacion, $avisar] = DB::transaction(function () use ($usuario, $texto): array {
            // Se bloquea la cuenta para que dos envíos simultáneos no creen dos
            // conversaciones abiertas.
            User::query()->whereKey($usuario->getKey())->lockForUpdate()->first();

            $conversacion = SupportConversation::query()
                ->where('user_id', $usuario->getKey())
                ->where('status', SupportConversationStatus::Open)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($conversacion === null) {
                $conversacion = SupportConversation::query()->create([
                    'user_id' => $usuario->getKey(),
                    'status' => SupportConversationStatus::Open,
                    'last_message_at' => now(),
                ]);
            }

            // Solo se avisa cuando el mensaje deja el hilo sin leer: el primero
            // de una conversación o el primero después de que el auditor lo abrió.
            $avisar = ! $conversacion->messages()
                ->where('sender_type', SupportMessage::AUTOR_USUARIO)
                ->whereNull('read_at')
                ->exists();

            $conversacion->messages()->create([
                'sender_id' => $usuario->getKey(),
                'sender_type' => SupportMessage::AUTOR_USUARIO,
                'body' => $texto,
            ]);

            $conversacion->forceFill(['last_message_at' => now()])->save();

            return [$conversacion, $avisar];
        });

        if ($avisar) {
            try {
                $this->notificar->ejecutar(
                    'soporte_mensaje',
                    'Nuevo mensaje de soporte',
                    "{$usuario->name} ({$usuario->role->label()}): ".Str::limit($texto, 120),
                    route('panel.soporte.show', $conversacion),
                    [
                        'conversation_id' => $conversacion->id,
                        'user_id' => $usuario->id,
                    ],
                );
            } catch (Throwable $error) {
                // El mensaje ya quedó guardado; un fallo del aviso no debe
                // impedir que la persona reciba respuesta.
                Log::warning('No se pudo avisar al auditor del mensaje de soporte.', [
                    'conversation_id' => $conversacion->id,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Tu mensaje fue enviado al equipo de soporte.',
            'data' => $this->formatear($conversacion, $request),
        ]);
    }

    private function usuarioDeLaApp(Request $request): User
    {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User && ($usuario->isPassenger() || $usuario->isDriver()),
            403,
            'El chat de soporte es para pasajeros y conductores.',
        );

        return $usuario;
    }

    private function conversacionAbierta(User $usuario): ?SupportConversation
    {
        return SupportConversation::query()
            ->where('user_id', $usuario->getKey())
            ->where('status', SupportConversationStatus::Open)
            ->latest('id')
            ->first();
    }

    /** @return array<string, mixed> */
    private function formatear(SupportConversation $conversacion, Request $request): array
    {
        $conversacion->load([
            'messages' => fn ($query) => $query->with('sender')->oldest('id'),
        ]);

        return (new SupportConversationResource($conversacion))->resolve($request);
    }
}
