<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Wallet\Actions\AprobarRecarga;
use App\Domain\Wallet\Actions\RechazarRecarga;
use App\Enums\RechargeStatus;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Recharge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recargas con comprobante: el auditor verifica la transferencia, aplica la
 * tasa del día y acredita la billetera.
 */
class RechargeController extends Controller
{
    public const TABS = [
        'pending' => 'Por aprobar',
        'approved' => 'Aprobadas',
        'rejected' => 'Rechazadas',
        'todas' => 'Todas',
    ];

    public function index(Request $request): View
    {
        $tab = $this->tabValida($request->query('tab'));
        $busqueda = trim((string) $request->query('buscar', ''));

        $recargas = Recharge::query()
            ->with(['user', 'reviewer'])
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $termino = '%'.mb_strtolower($busqueda).'%';

                $query->where(function (Builder $query) use ($termino): void {
                    $query->where('reference', 'like', '%'.mb_strtoupper($busqueda).'%')
                        ->orWhereHas('user', function (Builder $query) use ($termino): void {
                            $query->whereRaw('lower(first_name) like ?', [$termino])
                                ->orWhereRaw('lower(last_name) like ?', [$termino])
                                ->orWhereRaw('lower(email) like ?', [$termino])
                                ->orWhere('national_id', 'like', '%'.preg_replace('/\D/', '', $busqueda).'%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('panel.recargas.index', [
            'recargas' => $recargas,
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'contadores' => $this->contadores(),
        ]);
    }

    public function show(Recharge $recharge): View
    {
        $recharge->load(['user.wallet', 'reviewer']);

        return view('panel.recargas.show', [
            'recarga' => $recharge,
            'tasaVigente' => ExchangeRate::vigente(),
            'referenciaRepetida' => $recharge->referenceWasUsedBefore(),
            'otrasConLaMismaReferencia' => Recharge::query()
                ->whereKeyNot($recharge->getKey())
                ->whereRaw('lower(bank) = ?', [mb_strtolower((string) $recharge->bank)])
                ->whereRaw('lower(reference) = ?', [mb_strtolower((string) $recharge->reference)])
                ->with('user')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function approve(Request $request, Recharge $recharge, AprobarRecarga $accion): RedirectResponse
    {
        $datos = $request->validate([
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
        ], messages: [
            'exchange_rate.numeric' => 'La tasa debe ser un número.',
            'exchange_rate.gt' => 'La tasa debe ser mayor que cero.',
        ]);

        try {
            $accion->ejecutar(
                $recharge,
                $request->user(),
                isset($datos['exchange_rate']) ? (float) $datos['exchange_rate'] : null,
            );
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        $recharge->refresh();

        return redirect()
            ->route('panel.recargas.show', $recharge)
            ->with('status', 'Recarga aprobada: se acreditaron $'.number_format((float) $recharge->amount_usd, 2, '.', ',')." a {$recharge->user->name}.");
    }

    public function reject(Request $request, Recharge $recharge, RechazarRecarga $accion): RedirectResponse
    {
        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo del rechazo: la persona lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        try {
            $accion->ejecutar($recharge, $request->user(), $datos['reason']);
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('status', "Rechazaste la recarga de {$recharge->user->name}. No se acreditó saldo.");
    }

    /** Comprobante privado, solo por URL firmada. */
    public function proof(Recharge $recharge): StreamedResponse
    {
        $disk = Storage::disk($recharge->proof_disk);

        abort_unless($disk->exists($recharge->proof_path), 404, 'El comprobante ya no está disponible.');

        return $disk->response(
            $recharge->proof_path,
            $recharge->proof_original_name ?? basename($recharge->proof_path),
            ['Content-Type' => $recharge->proof_mime_type ?? 'application/octet-stream'],
        );
    }

    /** @param  Builder<Recharge>  $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'pending' => $query->where('status', RechargeStatus::Pending),
            'approved' => $query->where('status', RechargeStatus::Approved),
            'rejected' => $query->where('status', RechargeStatus::Rejected),
            default => null,
        };
    }

    private function tabValida(mixed $tab): string
    {
        return is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'pending';
    }

    /** @return array<string, int> */
    private function contadores(): array
    {
        return [
            'pending' => Recharge::query()->where('status', RechargeStatus::Pending)->count(),
            'approved' => Recharge::query()->where('status', RechargeStatus::Approved)->count(),
            'rejected' => Recharge::query()->where('status', RechargeStatus::Rejected)->count(),
        ];
    }
}
