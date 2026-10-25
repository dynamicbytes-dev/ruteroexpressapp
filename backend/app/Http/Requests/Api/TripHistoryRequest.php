<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del historial de viajes.
 *
 * El período es opcional: sin él se devuelve el historial completo, igual que
 * antes. Los límites se calculan en la zona horaria de la aplicación.
 */
class TripHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'El período debe ser día, semana o mes.',
        ];
    }

    public function attributes(): array
    {
        return [
            'period' => 'período',
        ];
    }
}
