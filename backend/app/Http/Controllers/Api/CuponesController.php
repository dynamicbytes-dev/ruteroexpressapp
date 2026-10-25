<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ValidarCuponRequest;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

/**
 * Validación de cupones de descuento para la app móvil.
 *
 * El pasajero envía un código y recibe el monto del descuento y lo que
 * deberá pagar. El cupón solo se consume realmente cuando el viaje se
 * crea (`POST /api/viajes`).
 */
class CuponesController extends Controller
{
    public function __construct(private readonly CouponService $cupones) {}

    /**
     * Valida un cupón para el usuario autenticado y un monto de viaje.
     *
     * Body: { "code": "DESCUENTO50", "monto": 10.00 }
     */
    public function validar(ValidarCuponRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $monto = (float) $request->validated('monto');
        $codigo = $request->validated('code');

        $resultado = $this->cupones->validar($codigo, $usuario, $monto);

        if (! $resultado['valid']) {
            return response()->json([
                'message' => $resultado['message'],
            ], 422);
        }

        return response()->json([
            'data' => [
                'code' => $resultado['coupon']->code,
                'discount_percent' => $resultado['discount_percent'],
                'discount_amount' => $resultado['discount_amount'],
                'payable_amount' => $resultado['payable_amount'],
            ],
        ]);
    }
}
