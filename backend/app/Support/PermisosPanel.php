<?php

namespace App\Support;

/**
 * Permisos finos del panel del auditor.
 *
 * El rol sigue viviendo en `users.role` (única fuente de verdad). Spatie se
 * usa encima para separar las secciones nuevas del panel y poder dar acceso
 * parcial a futuros operadores sin tocar el rol de la cuenta.
 */
final class PermisosPanel
{
    public const CONDUCTORES = 'panel.conductores';

    public const REPORTES = 'panel.reportes';

    public const AUDITORIA = 'panel.auditoria';

    /** @return array<int, string> */
    public static function todos(): array
    {
        return [
            self::CONDUCTORES,
            self::REPORTES,
            self::AUDITORIA,
        ];
    }

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            self::CONDUCTORES => 'Ver el panel de conductores',
            self::REPORTES => 'Ver reportes y exportar',
            self::AUDITORIA => 'Ver la auditoría del sistema',
        ];
    }
}
