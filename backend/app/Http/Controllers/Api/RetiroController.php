<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Actions\SolicitarRetiro;
use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWithdrawalRequest;
use App\Http\Resources\WithdrawalResource;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Retiros de ganancias de los conductores. */
class RetiroController extends Controller
{
    public function __construct(private readonly SolicitarRetiro $solicitar) {}

    public function index(Request $request): JsonResponse
    {
        return WithdrawalResource::collection(
            Withdrawal::query()
                ->where('user_id', $request->user()->getKey())
                ->latest()
                ->paginate(20),
        )->response();
    }

    public function store(StoreWithdrawalRequest $request): JsonResponse
    {
        try {
            $retiro = $this->solicitar->ejecutar(
                $request->user(),
                $request->safe()->only([
                    'amount_usd', 'bank', 'account_number', 'account_holder', 'account_id',
                ]),
            );
        } catch (OperacionNoPermitidaException $error) {
            return response()->json(['message' => $error->getMessage()], 403);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Recibimos tu solicitud. El monto quedó retenido mientras el equipo hace la transferencia.',
            'data' => ['withdrawal' => WithdrawalResource::make($retiro)],
        ], 201);
    }
}
