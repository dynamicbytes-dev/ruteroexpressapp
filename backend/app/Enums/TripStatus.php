<?php

namespace App\Enums;

/**
 * Ciclo de vida de un viaje.
 *
 * `offered` significa que hay una alerta enviada a un conductor; el viaje
 * todavía no tiene conductor asignado hasta que la acepta.
 */
enum TripStatus: string
{
    case Searching = 'searching';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Arriving = 'arriving';
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoDriver = 'no_driver';

    public function label(): string
    {
        return match ($this) {
            self::Searching => 'Buscando conductor',
            self::Offered => 'Alerta enviada',
            self::Accepted => 'Aceptado',
            self::Arriving => 'En camino',
            self::Waiting => 'Esperando al pasajero',
            self::InProgress => 'En curso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
            self::NoDriver => 'Sin conductor',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Searching, self::Offered => 'amber',
            self::Accepted, self::Arriving, self::Waiting => 'purple',
            self::InProgress => 'cyan',
            self::Completed => 'green',
            self::Cancelled, self::NoDriver => 'slate',
        };
    }

    /** Estados en los que el viaje sigue ocupando al pasajero y al conductor. */
    public static function active(): array
    {
        return [
            self::Searching,
            self::Offered,
            self::Accepted,
            self::Arriving,
            self::Waiting,
            self::InProgress,
        ];
    }

    /** Estados en los que el conductor ya está comprometido con el viaje. */
    public static function assigned(): array
    {
        return [
            self::Accepted,
            self::Arriving,
            self::Waiting,
            self::InProgress,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isAssigned(): bool
    {
        return in_array($this, self::assigned(), true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoDriver], true);
    }
}
