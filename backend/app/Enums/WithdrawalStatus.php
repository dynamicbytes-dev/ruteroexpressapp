<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En proceso',
            self::Paid => 'Pagado',
            self::Rejected => 'Rechazado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Paid => 'cyan',
            self::Rejected => 'red',
        };
    }
}
