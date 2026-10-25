<?php

namespace App\Console\Commands;

use App\Domain\Trips\TripDispatcher;
use Illuminate\Console\Command;

/**
 * Cierra las alertas que nadie respondió y continúa la cascada. Sin este
 * comando, la cascada solo avanzaría cuando la app volviera a consultar.
 */
class ExpirarOfertas extends Command
{
    protected $signature = 'viajes:expirar-ofertas';

    protected $description = 'Vence las alertas de viaje sin respuesta y pasa al siguiente conductor';

    public function handle(TripDispatcher $asignador): int
    {
        $cantidad = $asignador->expirarVencidas();

        $this->info("Alertas vencidas procesadas: {$cantidad}");

        return self::SUCCESS;
    }
}
