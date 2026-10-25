<?php

namespace App\Http\Controllers\Panel;

use App\Enums\PaymentMethod;
use App\Enums\RechargeStatus;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Recharge;
use App\Models\Trip;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes operativos y financieros del panel.
 *
 * Todo se calcula sobre datos ya confirmados: viajes completados, recargas
 * aprobadas, retiros pagados y movimientos del libro mayor. No modifica nada.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $rango = $this->rango($request);
        $desde = $rango['desde'];
        $hasta = $rango['hasta'];

        $viajes = Trip::query()
            ->where('status', TripStatus::Completed)
            ->whereBetween('completed_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()]);

        $resumenViajes = (clone $viajes)
            ->selectRaw('count(*) as cantidad')
            ->selectRaw('coalesce(sum(total), 0) as total')
            ->selectRaw('coalesce(sum(commission_amount), 0) as comision')
            ->selectRaw('coalesce(sum(driver_earning), 0) as ganancia')
            ->first();

        $porMetodo = (clone $viajes)
            ->selectRaw('payment_method, count(*) as cantidad, coalesce(sum(total), 0) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy(fn ($fila): string => $fila->payment_method instanceof PaymentMethod
                ? $fila->payment_method->value
                : (string) $fila->payment_method);

        $recargas = Recharge::query()
            ->where('status', RechargeStatus::Approved)
            ->whereBetween('reviewed_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()]);

        $retiros = Withdrawal::query()
            ->where('status', WithdrawalStatus::Paid)
            ->whereBetween('reviewed_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()]);

        $ajustes = WalletTransaction::query()
            ->where('type', WalletTransactionType::Adjustment)
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->selectRaw("coalesce(sum(case when direction = 'credit' then amount else -amount end), 0) as neto")
            ->selectRaw('count(*) as cantidad')
            ->first();

        $nuevos = User::query()
            ->whereBetween('created_at', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->selectRaw('role, count(*) as cantidad')
            ->groupBy('role')
            ->get()
            ->keyBy(fn ($fila): string => $fila->role instanceof UserRole ? $fila->role->value : (string) $fila->role);

        $topConductores = (clone $viajes)
            ->whereNotNull('driver_id')
            ->selectRaw('driver_id, count(*) as viajes, coalesce(sum(total), 0) as total, coalesce(sum(driver_earning), 0) as ganancia')
            ->groupBy('driver_id')
            ->orderByDesc('viajes')
            ->orderByDesc('total')
            ->limit(10)
            ->with('driver')
            ->get();

        $ultimos = (clone $viajes)
            ->with(['passenger', 'driver'])
            ->latest('completed_at')
            ->limit(15)
            ->get();

        return view('panel.reportes.index', [
            'rango' => $rango,
            'resumen' => [
                'viajes' => (int) ($resumenViajes->cantidad ?? 0),
                'total' => (float) ($resumenViajes->total ?? 0),
                'comision' => (float) ($resumenViajes->comision ?? 0),
                'ganancia' => (float) ($resumenViajes->ganancia ?? 0),
                'ticket' => (int) ($resumenViajes->cantidad ?? 0) > 0
                    ? (float) $resumenViajes->total / (int) $resumenViajes->cantidad
                    : 0.0,
                'recargas' => (int) $recargas->count(),
                'recargas_usd' => (float) $recargas->sum('amount_usd'),
                'retiros' => (int) $retiros->count(),
                'retiros_usd' => (float) $retiros->sum('amount_usd'),
                'ajustes' => (int) ($ajustes->cantidad ?? 0),
                'ajustes_neto' => (float) ($ajustes->neto ?? 0),
                'pasajeros_nuevos' => (int) ($nuevos->get('passenger')?->cantidad ?? 0),
                'conductores_nuevos' => (int) ($nuevos->get('driver')?->cantidad ?? 0),
            ],
            'porMetodo' => $porMetodo,
            'topConductores' => $topConductores,
            'ultimos' => $ultimos,
        ]);
    }

    public function exportTrips(Request $request): StreamedResponse
    {
        $rango = $this->rango($request);
        $nombre = 'viajes_'.$rango['desde']->format('Ymd').'_'.$rango['hasta']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rango): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Viaje', 'Finalizado', 'Pasajero', 'Conductor', 'Origen', 'Destino',
                'Pago', 'Total USD', 'Comisión USD', 'Ganancia conductor USD',
            ], ';');

            Trip::query()
                ->where('status', TripStatus::Completed)
                ->whereBetween('completed_at', [$rango['desde']->copy()->startOfDay(), $rango['hasta']->copy()->endOfDay()])
                ->with(['passenger', 'driver'])
                ->orderBy('completed_at')
                ->chunk(200, function (Collection $viajes) use ($salida): void {
                    foreach ($viajes as $viaje) {
                        fputcsv($salida, [
                            $viaje->id,
                            $viaje->completed_at?->format('d/m/Y H:i'),
                            $viaje->passenger?->name,
                            $viaje->driver?->name,
                            $viaje->origin_address,
                            $viaje->destination_address,
                            $viaje->payment_method?->label(),
                            number_format((float) $viaje->total, 2, '.', ''),
                            number_format((float) $viaje->commission_amount, 2, '.', ''),
                            number_format((float) $viaje->driver_earning, 2, '.', ''),
                        ], ';');
                    }
                });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportMovements(Request $request): StreamedResponse
    {
        $rango = $this->rango($request);
        $nombre = 'movimientos_'.$rango['desde']->format('Ymd').'_'.$rango['hasta']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rango): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Fecha', 'Persona', 'Tipo', 'Dirección', 'Monto USD', 'Saldo después', 'Descripción',
            ], ';');

            WalletTransaction::query()
                ->whereBetween('created_at', [$rango['desde']->copy()->startOfDay(), $rango['hasta']->copy()->endOfDay()])
                ->with('wallet.user')
                ->orderBy('created_at')
                ->chunk(200, function (Collection $movimientos) use ($salida): void {
                    foreach ($movimientos as $movimiento) {
                        fputcsv($salida, [
                            $movimiento->created_at?->format('d/m/Y H:i'),
                            $movimiento->wallet?->user?->name,
                            $movimiento->type?->label(),
                            $movimiento->direction,
                            number_format((float) $movimiento->amount, 2, '.', ''),
                            number_format((float) $movimiento->balance_after, 2, '.', ''),
                            $movimiento->description,
                        ], ';');
                    }
                });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    private function rango(Request $request): array
    {
        $desde = $this->fecha($request->query('desde')) ?? now()->startOfMonth();
        $hasta = $this->fecha($request->query('hasta')) ?? now();

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
