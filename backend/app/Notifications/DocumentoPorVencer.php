<?php

namespace App\Notifications;

use App\Models\UserDocument;
use Illuminate\Notifications\Notification;

/**
 * Aviso de un documento del conductor que está por vencer o ya venció.
 *
 * Se guarda en la tabla `notifications` para que la app lo pueda consultar
 * aunque no haya push (F4). El mismo registro servirá después para FCM.
 */
class DocumentoPorVencer extends Notification
{
    public function __construct(
        public readonly UserDocument $documento,
        public readonly int $diasRestantes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $etiqueta = $this->documento->type->label();
        $vence = $this->documento->expires_at?->format('d/m/Y') ?? '—';

        $titulo = $this->diasRestantes <= 0
            ? 'Documento vencido'
            : 'Documento por vencer';

        $mensaje = match (true) {
            $this->diasRestantes < 0 => "Tu {$etiqueta} venció el {$vence}. Vuelve a subirla para reactivar tu cuenta.",
            $this->diasRestantes === 0 => "Tu {$etiqueta} vence hoy ({$vence}). Vuelve a subirla para mantener tu cuenta activa.",
            default => "Tu {$etiqueta} vence en {$this->diasRestantes} días ({$vence}). Tenla lista para volver a subirla.",
        };

        return [
            'type' => 'documento_por_vencer',
            'title' => $titulo,
            'message' => $mensaje,
            'document_id' => $this->documento->getKey(),
            'document_type' => $this->documento->type->value,
            'document_label' => $etiqueta,
            'days_left' => $this->diasRestantes,
            'expires_at' => $this->documento->expires_at?->toDateString(),
        ];
    }
}
