<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de consulta y sincronización de la tasa oficial del
 * Banco Central de Venezuela (BCV).
 */
class BcvRateService
{
    /**
     * Obtiene la tasa oficial vigente del BCV.
     *
     * @return array{
     *     success: bool,
     *     rate: ?float,
     *     effective_date: ?string,
     *     source: string,
     *     message?: string,
     *     raw?: array<string, mixed>
     * }
     */
    public function obtenerTasaOficial(): array
    {
        // 1. Intento primario: DolarApi Oficial (BCV)
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->get('https://ve.dolarapi.com/v1/dolares/oficial');

            if ($response->successful()) {
                $data = $response->json();
                $tasa = (float) ($data['promedio'] ?? $data['precio'] ?? 0);

                if ($tasa > 0) {
                    $fecha = null;
                    if (! empty($data['fechaActualizacion'])) {
                        try {
                            $fecha = Carbon::parse($data['fechaActualizacion'])->toDateString();
                        } catch (\Throwable) {
                            $fecha = today()->toDateString();
                        }
                    } else {
                        $fecha = today()->toDateString();
                    }

                    // No permitir fecha futura
                    if ($fecha > today()->toDateString()) {
                        $fecha = today()->toDateString();
                    }

                    return [
                        'success' => true,
                        'rate' => round($tasa, 4),
                        'effective_date' => $fecha,
                        'source' => 'Banco Central de Venezuela (BCV Oficial vía DolarApi)',
                        'raw' => $data,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo al consultar DolarApi para tasa BCV: '.$e->getMessage());
        }

        // 2. Intento secundario: PyDolarVe
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->get('https://pydolarve.org/api/v1/dollar?page=bcv');

            if ($response->successful()) {
                $data = $response->json();
                $tasa = (float) ($data['monitors']['usd']['price'] ?? $data['price'] ?? 0);

                if ($tasa > 0) {
                    return [
                        'success' => true,
                        'rate' => round($tasa, 4),
                        'effective_date' => today()->toDateString(),
                        'source' => 'Banco Central de Venezuela (PyDolarVe)',
                        'raw' => $data,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo al consultar PyDolarVe para tasa BCV: '.$e->getMessage());
        }

        // 3. Intento terciario: Scraping directo de bcv.org.ve
        try {
            $response = Http::withoutVerifying()
                ->timeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get('https://www.bcv.org.ve');

            if ($response->successful()) {
                $html = $response->body();
                if (preg_match('/id=["\']dolar["\'][\s\S]*?<strong>\s*([0-9.,]+)\s*<\/strong>/i', $html, $matches)) {
                    $tasaStr = str_replace('.', '', $matches[1]);
                    $tasaStr = str_replace(',', '.', $tasaStr);
                    $tasa = (float) $tasaStr;

                    if ($tasa > 0) {
                        return [
                            'success' => true,
                            'rate' => round($tasa, 4),
                            'effective_date' => today()->toDateString(),
                            'source' => 'Banco Central de Venezuela (Portal Web BCV Oficial)',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Fallo al consultar web de BCV directamente: '.$e->getMessage());
        }

        return [
            'success' => false,
            'rate' => null,
            'effective_date' => null,
            'source' => 'Banco Central de Venezuela',
            'message' => 'No se pudo conectar con el servicio oficial del Banco Central de Venezuela en este momento. Puedes cargar la tasa manualmente.',
        ];
    }
}
