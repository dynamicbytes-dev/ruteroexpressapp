<?php

namespace App\Listeners;

use App\Events\NotificacionCreada;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Convierte una notificación de la bandeja en un push.
 *
 * El aviso completo vive en la tabla `notifications`; este listener lo lee y
 * usa su título y mensaje para el aviso del sistema. Así los avisos nuevos
 * (documentos, cuenta, etc.) no necesitan código extra de FCM.
 */
class EnviarPushDeNotificacion implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public function __construct(private readonly FcmService $fcm) {}

    public function handle(NotificacionCreada $event): void
    {
        if ($event->notificacionId === null) {
            return;
        }

        /** @var DatabaseNotification|null $notificacion */
        $notificacion = $event->usuario
            ->notifications()
            ->whereKey($event->notificacionId)
            ->first();

        if ($notificacion === null) {
            return;
        }

        $datos = is_array($notificacion->data) ? $notificacion->data : [];
        $titulo = (string) ($datos['title'] ?? 'GAZ');
        $cuerpo = (string) ($datos['message'] ?? '');

        if ($cuerpo === '') {
            return;
        }

        foreach ($event->usuario->deviceTokens()->pluck('token') as $token) {
            $this->fcm->enviar(
                $token,
                $titulo,
                $cuerpo,
                [
                    'tipo' => 'aviso',
                    'notification_id' => $event->notificacionId,
                    'aviso_type' => (string) ($datos['type'] ?? ''),
                ],
            );
        }
    }
}
