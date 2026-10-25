<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Solo se aceptan correos de Gmail.
 *
 * Se bloquea cualquier otro dominio (Hotmail, Outlook, Yahoo, corporativos, etc.).
 * La comparación ignora mayúsculas y espacios, y no se deja engañar por dominios
 * parecidos como "gmail.com.evil.io".
 */
class GmailOnlyEmail implements ValidationRule
{
    /**
     * Dominios permitidos. Se puede ajustar por configuración.
     *
     * @return array<int, string>
     */
    public static function allowedDomains(): array
    {
        $configured = (string) config('motoruta.allowed_email_domains', 'gmail.com');

        $domains = array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            explode(',', $configured)
        ));

        return $domains === [] ? ['gmail.com'] : array_values($domains);
    }

    public static function isAllowed(?string $email): bool
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return false;
        }

        $domain = strtolower(trim(substr($email, strrpos($email, '@') + 1)));

        return in_array($domain, self::allowedDomains(), true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::isAllowed(is_string($value) ? $value : null)) {
            return;
        }

        $permitidos = implode(', ', array_map(
            static fn (string $domain): string => '@'.$domain,
            self::allowedDomains()
        ));

        $fail("El correo debe ser de {$permitidos}. No se aceptan otros proveedores como Hotmail u Outlook.");
    }
}
