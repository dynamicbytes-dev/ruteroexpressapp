<?php

namespace App\Enums;

enum UserStatus: string
{
    /** Registrado, esperando que el auditor verifique sus documentos. */
    case Pending = 'pending';

    /** Verificado: puede operar (pedir viajes / activarse / recargar). */
    case Approved = 'approved';

    /** Uno o más documentos fueron rechazados; puede corregir y reenviar. */
    case Rejected = 'rejected';

    /** Suspendido por decisión del auditor. */
    case Suspended = 'suspended';

    /** Bloqueo temporal automático (penalización por rechazos de viaje). */
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En espera de verificación',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Suspended => 'Suspendido',
            self::Blocked => 'Bloqueado temporalmente',
        };
    }

    /** Color del estado en el panel del auditor. */
    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'cyan',
            self::Rejected, self::Suspended => 'red',
            self::Blocked => 'slate',
        };
    }

    /** ¿La cuenta puede operar en la aplicación? */
    public function canOperate(): bool
    {
        return $this === self::Approved;
    }
}
