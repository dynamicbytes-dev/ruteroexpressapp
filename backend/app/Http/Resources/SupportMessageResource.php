<?php

namespace App\Http\Resources;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mensaje de soporte visto desde la app.
 *
 * La app recibe el nombre visible del autor y el texto; no recibe correos ni
 * otros datos internos del auditor.
 *
 * @mixin SupportMessage
 */
class SupportMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->sender_type,
            'author_label' => $this->autorVisible(),
            'from_support' => $this->esDeAuditor(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
