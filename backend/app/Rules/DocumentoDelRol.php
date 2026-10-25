<?php

namespace App\Rules;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida que el tipo de documento exista y que corresponda al rol indicado.
 * Un pasajero no puede subir un RCV; un conductor no puede registrarse sin
 * los documentos del vehículo.
 */
class DocumentoDelRol implements ValidationRule
{
    public function __construct(
        private readonly string $rol,
        private readonly bool $exigirCompletos = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rol = UserRole::tryFrom($this->rol);

        if ($rol === null) {
            $fail('El rol indicado no es válido.');

            return;
        }

        $permitidos = array_map(
            static fn (DocumentType $tipo): string => $tipo->value,
            $rol->requiredDocuments(),
        );

        $recibidos = array_keys(is_array($value) ? $value : []);

        foreach ($recibidos as $tipo) {
            if (! in_array($tipo, $permitidos, true)) {
                $nombre = DocumentType::tryFrom((string) $tipo)?->label() ?? $tipo;
                $fail("El documento «{$nombre}» no corresponde a un usuario {$rol->label()}.");
            }
        }

        if (! $this->exigirCompletos) {
            return;
        }

        $faltantes = array_diff($permitidos, $recibidos);

        if ($faltantes !== []) {
            $etiquetas = array_map(
                static fn (string $tipo): string => DocumentType::from($tipo)->label(),
                $faltantes,
            );

            $fail('Faltan documentos obligatorios: '.implode(', ', $etiquetas).'.');
        }
    }
}
