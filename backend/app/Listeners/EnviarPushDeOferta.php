<?php

namespace App\Listeners;

use App\Events\OfertaViajeCreada;
use App\Models\TripOffer;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa por push al conductor cuando se le ofrece un viaje.
 *
 * El listener va a la cola para que el mensaje salga después de que la
 * transacción del viaje haya confirmado. Antes de enviar se revisa que la
 * oferta siga pendiente y que no haya vencido.
 */
class EnviarPushDeOferta implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public function __construct(private readonly FcmService $fcm) {}

    public function handle(OfertaViajeCreada $event): void
    {
        /** @var TripOffer|null $oferta */
        $oferta = $event->oferta->fresh(['trip', 'driver']);

        if ($oferta === null
            || ! $oferta->isPending()
            || $oferta->expires_at?->isPast()) {
            return;
        }

        $conductor = $oferta->driver;

        if ($conductor === null) {
            return;
        }

        $distancia = number_format((float) $oferta->distance_to_pickup_km, 1, ',', '.');
        $ganancia = number_format((float) ($oferta->trip?->driver_earning ?? 0), 2, ',', '.');
        $cuerpo = "Recogida a {$distancia} km. Ganas \${$ganancia}.";

        foreach ($conductor->deviceTokens()->pluck('token') as $token) {
            $this->fcm->enviar(
                $token,
                'Nuevo viaje cerca de ti',
                $cuerpo,
                [
                    'tipo' => 'oferta',
                    'trip_id' => $oferta->trip_id,
                    'offer_id' => $oferta->getKey(),
                    'expires_at' => $oferta->expires_at?->toIso8601String(),
                ],
            );
        }
    }
}
