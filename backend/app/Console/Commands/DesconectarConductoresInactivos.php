<?php

namespace App\Console\Commands;

use App\Domain\Trips\TripDispatcher;
use App\Models\DriverProfile;
use Illuminate\Console\Command;

/**
 * Desconecta a los conductores que dejaron de reportar ubicación.
 *
 * Evita que una app cerrada o sin conexión siga apareciendo en línea y reciba
 * alertas que nunca va a contestar. No borra la última posición: solo apaga
 * `is_online` y cancela ofertas pendientes.
 */
class DesconectarConductoresInactivos extends Command
{
    protected $signature = 'conductores:desconectar-inactivos';

    protected $description = 'Marca desconectados a los conductores cuya ubicación dejó de actualizarse';

    public function __construct(private readonly TripDispatcher $asignador)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $minutos = max(1, (int) config('motoruta.drivers.offline_after_minutes', 5));
        $limite = now()->subMinutes($minutos);
        $desconectados = 0;

        DriverProfile::query()
            ->where('is_online', true)
            ->where(function ($query) use ($limite): void {
                $query->whereNull('last_location_at')
                    ->orWhere('last_location_at', '<', $limite);
            })
            ->with('user')
            ->chunkById(100, function ($perfiles) use (&$desconectados): void {
                foreach ($perfiles as $perfil) {
                    $perfil->forceFill([
                        'is_online' => false,
                        'went_online_at' => null,
                    ])->save();

                    if ($perfil->user !== null) {
                        $this->asignador->cancelarOfertaPendienteDeConductor($perfil->user);
                    }

                    $desconectados++;
                }
            });

        $this->info("Conductores desconectados: {$desconectados}.");

        return self::SUCCESS;
    }
}
