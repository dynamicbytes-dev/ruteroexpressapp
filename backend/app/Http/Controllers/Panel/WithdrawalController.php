<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Wallet\Actions\PagarRetiro;
use App\Domain\Wallet\Actions\RechazarRetiro;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Retiros de conductores: el auditor transfiere, sube el comprobante y marca
 * como pagado (o rechaza y se libera el monto retenido).
 */
class WithdrawalController extends Controller
{
    public const TABS = [
        'pending' => 'En proceso',
        'paid' => 'Pagados',
        'rejected' => 'Rechazados',
        'todas' => 'Todos',
    ];

    public function index(Request $request): View
    {
        $tab = $this->tabValida($request->query('tab'));
        $busqueda = trim((string) $request->query('buscar', ''));

        $retiros = Withdrawal::query()
            ->with(['user', 'reviewer'])
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $termino = '%'.mb_strtolower($busqueda).'%';

                $query->whereHas('user', function (Builder $query) use ($termino): void {
                    $query->whereRaw('lower(first_name) like ?', [$termino])
                        ->orWhereRaw('lower(last_name) like ?', [$termino])
                        ->orWhereRaw('lower(email) like ?', [$termino])
                        ->orWhere('national_id', 'like', '%'.preg_replace('/\D/', '', $busqueda).'%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('panel.retiros.index', [
            'retiros' => $retiros,
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'contadores' => $this->contadores(),
        ]);
    }

    public function show(Withdrawal $withdrawal): View
    {
        $withdrawal->load(['user.wallet', 'reviewer']);

        return view('panel.retiros.show', [
            'retiro' => $withdrawal,
        ]);
    }

    public function pay(Request $request, Withdrawal $withdrawal, PagarRetiro $accion): RedirectResponse
    {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144'],
        ], messages: [
            'proof.required' => 'Adjunta el comprobante de la transferencia.',
            'proof.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
            'proof.max' => 'El comprobante no puede pesar más de 6 MB.',
        ]);

        try {
            $accion->ejecutar($withdrawal, $request->user(), $request->file('proof'));
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()
            ->route('panel.retiros.show', $withdrawal)
            ->with('status', "Retiro pagado a {$withdrawal->user->name}. Se descontaron $".number_format((float) $withdrawal->amount_usd, 2, '.', ',').' de su billetera.');
    }

    public function reject(Request $request, Withdrawal $withdrawal, RechazarRetiro $accion): RedirectResponse
    {
        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo del rechazo: la persona lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        try {
            $accion->ejecutar($withdrawal, $request->user(), $datos['reason']);
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('status', "Rechazaste el retiro de {$withdrawal->user->name}. El monto volvió a estar disponible.");
    }

    public function proof(Withdrawal $withdrawal): StreamedResponse
    {
        abort_if($withdrawal->proof_path === null, 404, 'Este retiro todavía no tiene comprobante.');

        $disk = Storage::disk($withdrawal->proof_disk);

        abort_unless($disk->exists($withdrawal->proof_path), 404, 'El comprobante ya no está disponible.');

        return $disk->response(
            $withdrawal->proof_path,
            $withdrawal->proof_original_name ?? basename($withdrawal->proof_path),
            ['Content-Type' => $withdrawal->proof_mime_type ?? 'application/octet-stream'],
        );
    }

    /** @param  Builder<Withdrawal>  $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'pending' => $query->where('status', WithdrawalStatus::Pending),
            'paid' => $query->where('status', WithdrawalStatus::Paid),
            'rejected' => $query->where('status', WithdrawalStatus::Rejected),
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
            'pending' => Withdrawal::query()->where('status', WithdrawalStatus::Pending)->count(),
            'paid' => Withdrawal::query()->where('status', WithdrawalStatus::Paid)->count(),
            'rejected' => Withdrawal::query()->where('status', WithdrawalStatus::Rejected)->count(),
        ];
    }
}
