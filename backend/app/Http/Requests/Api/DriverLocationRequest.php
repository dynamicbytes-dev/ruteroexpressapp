<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DriverLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'bearing' => ['nullable', 'integer', 'between:0,359'],
            'speed_kmh' => ['nullable', 'numeric', 'between:0,200'],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Falta tu ubicación.',
            'lng.required' => 'Falta tu ubicación.',
            'lat.between' => 'La latitud no es válida.',
            'lng.between' => 'La longitud no es válida.',
            'bearing.between' => 'El rumbo debe estar entre 0 y 359 grados.',
            'speed_kmh.between' => 'La velocidad no es válida.',
        ];
    }
}
