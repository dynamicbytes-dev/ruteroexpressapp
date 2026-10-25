<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Services\BcvRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tasa de cambio oficial y manual. Cada cambio queda como una fila nueva: el histórico
 * completo se conserva y la tasa vigente es la última con fecha de hoy o antes.
 * Permite sincronización directa con la API del Banco Central de Venezuela (BCV).
 */
class ExchangeRateController extends Controller
{
    public function index(BcvRateService $bcvService): View
    {
        $bcvInfo = null;
        try {
            $bcvInfo = $bcvService->obtenerTasaOficial();
        } catch (\Throwable) {
            // No bloquear la vista si la conexión externa falla
        }

        return view('panel.tasa.index', [
            'vigente' => ExchangeRate::vigente(),
            'bcv' => $bcvInfo,
            'historial' => ExchangeRate::query()
                ->with('author')
                ->latest('effective_date')
                ->latest('id')
                ->paginate(30),
        ]);
    }

    public function bcvConsulta(BcvRateService $bcvService): JsonResponse
    {
        $info = $bcvService->obtenerTasaOficial();

        return response()->json($info);
    }

    public function sincronizarBcv(Request $request, BcvRateService $bcvService): RedirectResponse
    {
        $resultado = $bcvService->obtenerTasaOficial();

        if (! $resultado['success'] || empty($resultado['rate'])) {
            return redirect()
                ->route('panel.tasa.index')
                ->withErrors(['rate' => $resultado['message'] ?? 'No se pudo obtener la tasa oficial del BCV en este momento.']);
        }

        $fecha = $resultado['effective_date'] ?? today()->toDateString();
        $tasa = (float) $resultado['rate'];

        ExchangeRate::create([
            'effective_date' => $fecha,
            'rate' => $tasa,
            'created_by' => $request->user()->getKey(),
        ]);

        activity('tasa_cambio')
            ->causedBy($request->user())
            ->withProperties([
                'rate' => $tasa,
                'effective_date' => $fecha,
                'fuente' => $resultado['source'],
            ])
            ->log('Tasa de cambio sincronizada automáticamente con el Banco Central de Venezuela (BCV)');

        return redirect()
            ->route('panel.tasa.index')
            ->with('status', "Tasa sincronizada con éxito desde el Banco Central de Venezuela (BCV): {$tasa} Bs / $. Aplica desde {$fecha}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ], messages: [
            'effective_date.required' => 'Indica a partir de qué fecha aplica la tasa.',
            'effective_date.before_or_equal' => 'No puedes cargar una tasa con fecha futura.',
            'rate.required' => 'Escribe la tasa en bolívares por dólar.',
            'rate.gt' => 'La tasa debe ser mayor que cero.',
        ]);

        ExchangeRate::create([
            'effective_date' => $datos['effective_date'],
            'rate' => $datos['rate'],
            'created_by' => $request->user()->getKey(),
        ]);

        return redirect()
            ->route('panel.tasa.index')
            ->with('status', 'Tasa de cambio actualizada. A partir de ahora se usa para nuevas recargas y conversiones.');
    }
}
