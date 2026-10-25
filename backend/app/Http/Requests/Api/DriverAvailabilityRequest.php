<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DriverAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'online' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'online.required' => 'Indica si quieres conectarte o desconectarte.',
            'online.boolean' => 'El estado de conexión no es válido.',
        ];
    }
}
