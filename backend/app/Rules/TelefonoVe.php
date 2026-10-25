<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Teléfono de Venezuela: 04XX-XXXXXXX (11 dígitos) o formato internacional +58.
 * Se guarda siempre normalizado a 11 dígitos: 04121234567.
 */
class TelefonoVe implements ValidationRule
{
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        // +58 412 1234567 -> 04121234567
        if (str_starts_with($digitos, '58') && strlen($digitos) === 12) {
            $digitos = '0'.substr($digitos, 2);
        }

        return $digitos;
    }

    public static function esValido(?string $valor): bool
    {
        $digitos = self::normalizar($valor);

        // Móvil venezolano: 04 seguido de 9 dígitos (0412, 0414, 0416, 0424, 0426).
        return $digitos !== null && preg_match('/^04\d{9}$/', $digitos) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::esValido(is_string($value) ? $value : null)) {
            return;
        }

        $fail('El teléfono debe ser un número móvil de Venezuela, por ejemplo 0412-1234567.');
    }
}
