<?php

namespace App\Events;

use App\Models\TripOffer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso en tiempo real para el conductor al que se le acaba de ofrecer un
 * viaje. La app recibe la señal y pide las alertas pendientes a la API.
 *
 * Se emite solo al canal privado del conductor. El pasajero no ve la alerta.
 */
class OfertaViajeCreada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly TripOffer $oferta) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->oferta->driver_id);
    }

    public function broadcastAs(): string
    {
        return 'oferta.viaje';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'offer_id' => $this->oferta->id,
            'trip_id' => $this->oferta->trip_id,
            'driver_id' => $this->oferta->driver_id,
            'distance_to_pickup_km' => (float) $this->oferta->distance_to_pickup_km,
            'expires_at' => $this->oferta->expires_at?->toIso8601String(),
        ];
    }
}
