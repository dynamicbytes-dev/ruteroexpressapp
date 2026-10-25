<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso operativo para el panel del auditor.
 *
 * No sale por correo ni por push: vive en la campana del panel. El `url` se
 * guarda ya resuelto para que la bandeja pueda abrir el registro exacto.
 */
class AvisoAuditor extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $datos */
    public function __construct(
        public readonly string $tipo,
        public readonly string $titulo,
        public readonly string $mensaje,
        public readonly ?string $url = null,
        public readonly array $datos = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->tipo,
            'title' => $this->titulo,
            'message' => $this->mensaje,
            'url' => $this->url,
            'data' => $this->datos,
        ];
    }
}
