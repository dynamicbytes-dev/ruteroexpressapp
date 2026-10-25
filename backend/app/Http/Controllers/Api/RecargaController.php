<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Actions\SolicitarRecarga;
use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRechargeRequest;
use App\Http\Resources\RechargeResource;
use App\Models\Recharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Recargas con comprobante de transferencia nacional. */
class RecargaController extends Controller
{
    public function __construct(private readonly SolicitarRecarga $solicitar) {}

    public function index(Request $request): JsonResponse
    {
        return RechargeResource::collection(
            Recharge::query()
                ->where('user_id', $request->user()->getKey())
                ->latest()
                ->paginate(20),
        )->response();
    }

    public function store(StoreRechargeRequest $request): JsonResponse
    {
        try {
            $recarga = $this->solicitar->ejecutar(
                $request->user(),
                $request->safe()->only(['amount_bs', 'bank', 'reference', 'transferred_at']),
                $request->file('comprobante'),
            );
        } catch (OperacionNoPermitidaException $error) {
            return response()->json(['message' => $error->getMessage()], 403);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Recibimos tu recarga. Queda pendiente hasta que el equipo verifique el comprobante.',
            'data' => ['recharge' => RechargeResource::make($recarga)],
        ], 201);
    }
}
