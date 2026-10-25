<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Config;

/**
 * Tasa de cambio del dólar (BCV).
 *
 * Consulta una fuente externa configurada en `config/motoruta.php`
 * (`exchange_rate.api_url`, por defecto el BCV) y guarda la tasa vigente
 * en `exchange_rates`. El comando `tasa:actualizar` se puede ejecutar
 * manualmente o programar para que corra automáticamente.
 */
class ExchangeRateService
{
    public function __construct(
        private int $timeout = 10,
    ) {}

    /**
     * Consulta la fuente externa y guarda la tasa del día.
     *
     * @return array{rate: float, effective_date: string, saved: bool}
     * @throws \RuntimeException si la fuente no responde o no se puede parsear.
     */
    public function actualizarDesdeExterna(?User $autor = null): array
    {
        $apiUrl = config('motoruta.exchange_rate.api_url', 'https://www.bcv.org.ve/tasas-cambio');

        $respuesta = Http::timeout($this->timeout)->get($apiUrl);

        if (! $respuesta->ok()) {
            throw new \RuntimeException(
                "La fuente de tasas respondió con código {$respuesta->status()}.",
            );
        }

        $cuerpo = $respuesta->body();
        $datos = $this->parsear($cuerpo);

        $tasa = (float) $datos['rate'];
        $fecha = $datos['effective_date'] ?? today()->toDateString();

        if ($tasa <= 0) {
            throw new \RuntimeException('La tasa obtenida no es válida.');
        }

        $existente = ExchangeRate::query()
            ->whereDate('effective_date', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($existente !== null && (float) $existente->rate === $tasa) {
            return [
                'rate' => $tasa,
                'effective_date' => $fecha,
                'saved' => false,
            ];
        }

        ExchangeRate::create([
            'rate' => $tasa,
            'effective_date' => $fecha,
            'created_by' => $autor?->getKey(),
        ]);

        return [
            'rate' => $tasa,
            'effective_date' => $fecha,
            'saved' => true,
        ];
    }

    /**
     * Parsea la respuesta de la fuente externa. Soporta JSON y HTML básico
     * (por si la página del BCV devuelve HTML sin API JSON).
     *
     * @return array{rate: float, effective_date?: string}
     */
    private function parsear(string $cuerpo): array
    {
        $decoded = json_decode($cuerpo, true);

        if (is_array($decoded)) {
            // Busca la tasa en claves comunes de APIs de cambio.
            $clavesRate = ['rate', 'valor', 'price', 'value', 'usd_ves', 'dolar', 'bcv'];
            $clavesFecha = ['date', 'fecha', 'effective_date', 'published_at'];

            $rate = null;
            foreach ($clavesRate as $clave) {
                if (isset($decoded[$clave]) && is_numeric($decoded[$clave])) {
                    $rate = (float) $decoded[$clave];
                    break;
                }
            }

            $fecha = null;
            foreach ($clavesFecha as $clave) {
                if (isset($decoded[$clave])) {
                    $fecha = $decoded[$clave];
                    break;
                }
            }

            if ($rate !== null && $rate > 0) {
                return ['rate' => $rate, 'effective_date' => $fecha];
            }
        }

        // Intento de parseo básico de HTML: busca un número con el formato
        // típico de tasa del BCV (ej. "36,50" o "36.50").
        if (preg_match('/(\d{1,3}[.,]\d{2})/', $cuerpo, $coincidencias)) {
            $valor = str_replace(',', '.', $coincidencias[1]);
            $tasa = (float) $valor;

            if ($tasa > 0) {
                return ['rate' => $tasa, 'effective_date' => today()->toDateString()];
            }
        }

        throw new \RuntimeException('No se pudo extraer la tasa de cambio de la respuesta.');
    }
}
