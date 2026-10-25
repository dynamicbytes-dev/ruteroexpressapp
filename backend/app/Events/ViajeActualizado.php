<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Señal de que un viaje cambió de estado (oferta, aceptación, llegada,
 * cancelación, finalización...). Se envía a las personas implicadas.
 *
 * El evento no lleva el viaje completo: cada app pide el detalle a la API con
 * su propio token, así el backend sigue decidiendo qué datos ve cada rol.
 */
class ViajeActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int|null>  $usuarios
     */
    public function __construct(
        public readonly Trip $viaje,
        public readonly array $usuarios,
        public readonly string $motivo = 'estado',
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $canales = [];

        foreach (array_unique(array_filter($this->usuarios)) as $usuario) {
            $canales[] = new PrivateChannel('App.Models.User.'.(int) $usuario);
        }

        return $canales;
    }

    public function broadcastAs(): string
    {
        return 'viaje.actualizado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->viaje->id,
            'status' => $this->viaje->status->value,
            'driver_id' => $this->viaje->driver_id,
            'motivo' => $this->motivo,
        ];
    }
}
