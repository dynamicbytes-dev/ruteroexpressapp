<?php

namespace App\Http\Resources;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversación de soporte vista desde la app.
 *
 * `auditor_name` identifica a la persona del equipo que atendió el chat. La app
 * lo muestra en el encabezado; no se exponen correos ni otros datos internos.
 *
 * @mixin SupportConversation
 */
class SupportConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'started_at' => $this->created_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'auditor_name' => $this->whenLoaded('messages', function (): ?string {
                $primeraRespuesta = $this->messages
                    ->firstWhere('sender_type', SupportMessage::AUTOR_AUDITOR);

                return $primeraRespuesta?->sender?->name;
            }),
            'messages' => SupportMessageResource::collection(
                $this->whenLoaded('messages'),
            )->resolve($request),
        ];
    }
}
