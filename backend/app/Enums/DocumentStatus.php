<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }

    /** Color del estado en el panel del auditor. */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'cyan',
            self::Rejected => 'red',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }
}
