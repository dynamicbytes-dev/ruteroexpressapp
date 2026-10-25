<?php

namespace App\Listeners;

use App\Enums\TripStatus;
use App\Events\ViajeActualizado;
use App\Models\Trip;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push de los cambios importantes de un viaje.
 *
 * No se manda un push por cada evento: las alertas nuevas las cubre
 * `EnviarPushDeOferta`. Aquí solo se avisa a la otra parte cuando el viaje
 * avanza, se cancela o termina, para que la app pueda reaccionar aunque esté
 * en segundo plano.
 */
class EnviarPushDeViaje implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public function __construct(private readonly FcmService $fcm) {}

    public function handle(ViajeActualizado $event): void
    {
        $viaje = $event->viaje->fresh();

        if ($viaje === null) {
            return;
        }

        $mensaje = $this->mensaje($event->motivo, $viaje);

        if ($mensaje === null) {
            return;
        }

        [$destinatarioId, $titulo, $cuerpo] = $mensaje;
        $destinatario = User::query()->find($destinatarioId);

        if ($destinatario === null) {
            return;
        }

        foreach ($destinatario->deviceTokens()->pluck('token') as $token) {
            $this->fcm->enviar(
                $token,
                $titulo,
                $cuerpo,
                [
                    'tipo' => 'viaje',
                    'trip_id' => $viaje->getKey(),
                    'status' => $viaje->status->value,
                    'motivo' => $event->motivo,
                ],
            );
        }
    }

    /**
     * @return array{0: int, 1: string, 2: string}|null
     */
    private function mensaje(string $motivo, Trip $viaje): ?array
    {
        $pasajero = $viaje->passenger_id;
        $conductor = $viaje->driver_id;
        $estado = $viaje->status;

        return match (true) {
            $motivo === 'aceptado' => [
                $pasajero,
                'Tu conductor aceptó',
                'El conductor va en camino al punto de recogida.',
            ],
            in_array($motivo, ['conductor_cancelo', 'conductor_desconectado'], true) => [
                $pasajero,
                'El conductor canceló',
                'Estamos buscando otro conductor para tu viaje.',
            ],
            $motivo === 'sin_conductor' => [
                $pasajero,
                'No encontramos conductor',
                'Puedes intentarlo de nuevo en un momento.',
            ],
            $motivo === 'cancelado' => $conductor === null
                ? null
                : [
                    $conductor,
                    'El pasajero canceló el viaje',
                    'El viaje fue cancelado por el pasajero.',
                ],
            $motivo === 'finalizado', $estado === TripStatus::Completed => [
                $pasajero,
                'Viaje finalizado',
                'Gracias por viajar con GAZ.',
            ],
            $motivo === 'estado' && $estado === TripStatus::Arriving => [
                $pasajero,
                'Tu conductor llegó',
                'Está en el punto de recogida.',
            ],
            $motivo === 'estado' && $estado === TripStatus::Waiting => [
                $pasajero,
                'El conductor te espera',
                'Acércate al punto de recogida para comenzar.',
            ],
            $motivo === 'estado' && $estado === TripStatus::InProgress => [
                $pasajero,
                'Viaje iniciado',
                'Vas en camino a tu destino.',
            ],
            default => null,
        };
    }
}
