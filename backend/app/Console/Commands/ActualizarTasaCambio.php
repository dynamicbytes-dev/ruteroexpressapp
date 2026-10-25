<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Actualiza la tasa de cambio del dólar desde el BCV (u otra fuente externa
 * configurada). Guarda la tasa en `exchange_rates` si es nueva.
 */
class ActualizarTasaCambio extends Command
{
    protected $signature = 'tasa:actualizar';

    protected $description = 'Consulta la fuente externa (BCV) y guarda la tasa de cambio del día';

    public function handle(ExchangeRateService $tasas): int
    {
        $autor = User::query()->where('role', 'auditor')->orderByDesc('id')->first();

        try {
            $resultado = $tasas->actualizarDesdeExterna($autor);
        } catch (\Throwable $error) {
            $this->components->error("Error actualizando la tasa: {$error->getMessage()}");

            return self::FAILURE;
        }

        if ($resultado['saved']) {
            $this->info(
                "Tasa del dólar actualizada: Bs {$resultado['rate']} por USD "
                ."efectiva desde {$resultado['effective_date']}.",
            );
        } else {
            $this->info(
                "Ya existe una tasa de Bs {$resultado['rate']} por USD "
                ."para {$resultado['effective_date']}; no se duplicó.",
            );
        }

        return self::SUCCESS;
    }
}
