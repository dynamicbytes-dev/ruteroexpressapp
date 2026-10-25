<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Validación y aplicación de cupones de descuento.
 *
 * Los cupones son creados por el auditor desde el panel y son porcentuales.
 * El descuento reduce lo que paga el pasajero; el conductor sigue recibiendo
 * su ganancia completa sobre el total original (la diferencia es un subsidio
 * del sistema).
 */
class CouponService
{
    /**
     * Valida un código de cupón para un usuario y un monto de viaje.
     *
     * @return array{valid: bool, coupon?: Coupon, discount_amount?: float, payable_amount?: float, message?: string}
     */
    public function validar(string $code, User $usuario, float $monto): array
    {
        $cupo = Coupon::query()->where('code', mb_strtoupper(trim($code)))->first();

        if ($cupo === null) {
            return ['valid' => false, 'message' => 'El código del cupón no existe.'];
        }

        if (! $cupo->puedeUsar($usuario)) {
            $mensaje = 'Este cupón ya no se puede usar.';

            if (! $cupo->is_active) {
                $mensaje = 'Este cupón está desactivado.';
            } elseif ($cupo->valid_from && $cupo->valid_from->isFuture()) {
                $mensaje = 'Este cupón aún no está vigente.';
            } elseif ($cupo->valid_until !== null && $cupo->valid_until->isPast()) {
                $mensaje = 'Este cupón expiró.';
            } elseif ($cupo->max_uses_global !== null
                && $cupo->usages()->count() >= $cupo->max_uses_global) {
                $mensaje = 'Este cupón alcanzó su límite de usos totales.';
            } elseif ($cupo->max_uses_per_user !== null
                && $cupo->usages()->where('user_id', $usuario->getKey())->count() >= $cupo->max_uses_per_user) {
                $mensaje = 'Ya alcanzaste el límite de usos de este cupón.';
            }

            return ['valid' => false, 'message' => $mensaje];
        }

        [$descuento, $pagar] = $cupo->calcularDescuento($monto);

        return [
            'valid' => true,
            'coupon' => $cupo,
            'discount_percent' => (float) $cupo->discount_percent,
            'discount_amount' => $descuento,
            'payable_amount' => $pagar,
        ];
    }

    /** Registra el uso de un cupón en un viaje. */
    public function registrarUso(Coupon $cupo, User $usuario, ?Trip $viaje = null): CouponUsage
    {
        return $cupo->registrarUso($usuario, $viaje);
    }
}
