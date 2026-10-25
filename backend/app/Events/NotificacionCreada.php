<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de que la bandeja tiene una notificación nueva.
 *
 * La app recibe la señal y refresca `GET /api/notificaciones`; el contenido
 * completo sigue viviendo en la base de datos.
 */
class NotificacionCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $usuario,
        public readonly ?string $notificacionId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->usuario->id);
    }

    public function broadcastAs(): string
    {
        return 'notificacion.creada';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notificacionId,
        ];
    }
}
