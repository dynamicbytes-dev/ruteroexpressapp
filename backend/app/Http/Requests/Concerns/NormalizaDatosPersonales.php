<?php

namespace App\Http\Requests\Concerns;

use App\Rules\TelefonoVe;

/**
 * Normaliza los datos personales antes de validar, para que la comparación
 * de unicidad y los formatos no dependan de cómo los escriba la persona:
 * el correo en minúsculas, la cédula y el teléfono sin puntos ni guiones,
 * y los nombres sin espacios repetidos.
 */
trait NormalizaDatosPersonales
{
    protected function normalizarPersonales(): void
    {
        $datos = [];

        if ($this->filled('email')) {
            $datos['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        if ($this->filled('national_id')) {
            $datos['national_id'] = preg_replace('/\D/', '', (string) $this->input('national_id'));
        }

        if ($this->filled('phone')) {
            $datos['phone'] = TelefonoVe::normalizar((string) $this->input('phone'));
        }

        foreach (['first_name', 'last_name'] as $campo) {
            if ($this->filled($campo)) {
                $datos[$campo] = preg_replace('/\s+/u', ' ', trim((string) $this->input($campo)));
            }
        }

        if ($this->filled('address')) {
            $datos['address'] = preg_replace('/\s+/u', ' ', trim((string) $this->input('address')));
        }

        if ($this->filled('vehicle_plate')) {
            $datos['vehicle_plate'] = strtoupper(preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                (string) $this->input('vehicle_plate'),
            ));
        }

        $this->merge($datos);
    }
}
