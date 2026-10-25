<?php

namespace App\Events;

use App\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Posición del conductor durante un viaje asignado.
 *
 * Solo se envía al pasajero del viaje. Las coordenadas son las mismas que el
 * conductor acaba de reportar a `POST /api/conductor/ubicacion`.
 */
class UbicacionConductorActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Trip $viaje,
        public readonly float $lat,
        public readonly float $lng,
        public readonly ?int $rumbo = null,
        public readonly ?float $velocidad = null,
        public readonly ?CarbonInterface $actualizadoEn = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->viaje->passenger_id);
    }

    public function broadcastAs(): string
    {
        return 'conductor.ubicacion';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->viaje->id,
            'driver_id' => $this->viaje->driver_id,
            'lat' => round($this->lat, 7),
            'lng' => round($this->lng, 7),
            'bearing' => $this->rumbo,
            'speed_kmh' => $this->velocidad,
            'updated_at' => ($this->actualizadoEn ?? now())->toIso8601String(),
        ];
    }
}
