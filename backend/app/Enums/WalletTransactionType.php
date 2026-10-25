<?php

namespace App\Enums;

/**
 * Tipos de movimiento del libro mayor de la billetera.
 *
 * El libro es de solo-inserción: un movimiento no se edita nunca. Si hay que
 * corregir algo, se compensa con un `adjustment`.
 */
enum WalletTransactionType: string
{
    case Recharge = 'recharge';
    case TripCharge = 'trip_charge';
    case TripEarning = 'trip_earning';
    case Commission = 'commission';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
    case Subsidy = 'subsidy';

    public function label(): string
    {
        return match ($this) {
            self::Recharge => 'Recarga',
            self::TripCharge => 'Pago de viaje',
            self::TripEarning => 'Ganancia de viaje',
            self::Commission => 'Comisión GAZ',
            self::Withdrawal => 'Retiro',
            self::Adjustment => 'Ajuste manual',
            self::Subsidy => 'Subsidio GAZ',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::Recharge, self::TripEarning, self::Subsidy => 'credit',
            self::TripCharge, self::Commission, self::Withdrawal => 'debit',
            self::Adjustment => 'credit',
        };
    }
}
