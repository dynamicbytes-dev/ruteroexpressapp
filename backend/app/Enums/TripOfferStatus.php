<?php

namespace App\Enums;

/** Estado de una alerta enviada a un conductor. */
enum TripOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Esperando respuesta',
            self::Accepted => 'Aceptada',
            self::Rejected => 'Rechazada',
            self::Expired => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function isFinished(): bool
    {
        return $this !== self::Pending;
    }
}
