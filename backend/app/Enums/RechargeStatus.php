<?php

namespace App\Enums;

enum RechargeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por revisar',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'cyan',
            self::Rejected => 'red',
        };
    }
}
