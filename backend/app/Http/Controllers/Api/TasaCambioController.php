<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tasa de cambio vigente, para refrescar la referencia en bolívares.
 */
class TasaCambioController extends Controller
{
    public function __construct(private readonly ExchangeRateService $tasas) {}

    public function show(): JsonResponse
    {
        $tasa = ExchangeRate::vigente();

        return response()->json([
            'data' => [
                'rate' => $tasa === null ? null : (float) $tasa->rate,
                'effective_date' => $tasa?->effective_date->toDateString(),
                'updated_at' => $tasa?->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Fuerza una actualización de la tasa desde la fuente externa (BCV).
     * Solo para auditores.
     */
    public function actualizar(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAuditor(), 403, 'Solo el auditor puede actualizar la tasa.');

        try {
            $resultado = $this->tasas->actualizarDesdeExterna($request->user());
        } catch (\Throwable $error) {
            return response()->json([
                'message' => 'No se pudo actualizar la tasa: '.$error->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $resultado['saved']
                ? 'Tasa actualizada desde el BCV: Bs '.$resultado['rate'].' por USD.'
                : 'Ya existe una tasa de Bs '.$resultado['rate'].' por USD para '.$resultado['effective_date'].'; no se duplicó.',
            'data' => [
                'rate' => $resultado['rate'],
                'effective_date' => $resultado['effective_date'],
                'saved' => $resultado['saved'],
            ],
        ]);
    }
}
