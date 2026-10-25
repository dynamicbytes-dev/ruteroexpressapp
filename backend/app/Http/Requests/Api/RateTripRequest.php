<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/** Calificación del pasajero al conductor, al terminar el viaje. */
class RateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'favorite' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Elige de 1 a 5 estrellas.',
            'rating.integer' => 'La calificación debe ser un número entero de 1 a 5.',
            'rating.between' => 'La calificación debe estar entre 1 y 5 estrellas.',
            'comment.max' => 'El comentario no puede pasar de 500 caracteres.',
            'favorite.boolean' => 'La marca de favorito no es válida.',
        ];
    }
}
