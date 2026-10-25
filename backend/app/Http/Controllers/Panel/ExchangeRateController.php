<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tasa de cambio manual. Cada cambio queda como una fila nueva: el histórico
 * completo se conserva y la tasa vigente es la última con fecha de hoy o antes.
 */
class ExchangeRateController extends Controller
{
    public function index(): View
    {
        return view('panel.tasa.index', [
            'vigente' => ExchangeRate::vigente(),
            'historial' => ExchangeRate::query()
                ->with('author')
                ->latest('effective_date')
                ->latest('id')
                ->paginate(30),
        ]);
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
