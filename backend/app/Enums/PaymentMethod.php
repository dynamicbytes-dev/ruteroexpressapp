<?php

namespace App\Enums;

/** Cómo paga el pasajero el viaje. */
enum PaymentMethod: string
{
    case Wallet = 'wallet';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'Billetera',
            self::Cash => 'Efectivo',
        };
    }
}
