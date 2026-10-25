<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Support\Configuracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Billetera del pasajero o conductor.
 *
 * El saldo lo calcula siempre el backend. La app solo muestra los números y
 * el equivalente en bolívares con la tasa vigente.
 */
class BilleteraController extends Controller
{
    public function __construct(private readonly WalletService $billetera) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $billetera = $this->billetera->paraUsuario($usuario);
        $tasa = ExchangeRate::vigente();

        $movimientos = $billetera->transactions()->limit(10)->get();

        return response()->json([
            'data' => [
                'balance' => (float) $billetera->balance,
                'held' => (float) $billetera->held_balance,
                'available' => $this->billetera->disponible($billetera),
                'currency' => (string) config('motoruta.currency.base', 'USD'),
                'symbol' => (string) config('motoruta.currency.symbol', '$'),
                'local_currency' => (string) config('motoruta.currency.local', 'VES'),
                'local_symbol' => (string) config('motoruta.currency.local_symbol', 'Bs'),
                'exchange_rate' => $tasa === null ? null : [
                    'rate' => (float) $tasa->rate,
                    'effective_date' => $tasa->effective_date->toDateString(),
                    'local_equivalent' => round((float) $billetera->balance * (float) $tasa->rate, 2),
                ],
                'limits' => [
                    'min_withdrawal_usd' => Configuracion::decimal('retiro_minimo_usd', 1),
                    'negative_cap_usd' => Configuracion::decimal('tope_saldo_negativo', -5),
                ],
                'bank' => $this->datosBancarios(),
                'recent_transactions' => WalletTransactionResource::collection($movimientos)->resolve(),
            ],
        ]);
    }

    public function movimientos(Request $request): JsonResponse
    {
        $billetera = $this->billetera->paraUsuario($request->user());

        return WalletTransactionResource::collection(
            $billetera->transactions()->paginate(25),
        )->response();
    }

    /** @return array<string, string> */
    private function datosBancarios(): array
    {
        return [
            'bank' => Configuracion::get('banco_nombre', ''),
            'account' => Configuracion::get('banco_cuenta', ''),
            'holder' => Configuracion::get('banco_titular', ''),
            'rif' => Configuracion::get('banco_rif', ''),
            'instructions' => Configuracion::get('banco_instrucciones', ''),
        ];
    }
}
