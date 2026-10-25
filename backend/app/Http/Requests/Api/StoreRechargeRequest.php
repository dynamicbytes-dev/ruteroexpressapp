<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreRechargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_bs' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'bank' => ['required', 'string', 'min:2', 'max:80'],
            'reference' => ['required', 'string', 'min:3', 'max:60'],
            'transferred_at' => ['required', 'date', 'before_or_equal:today'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_bs.required' => 'Indica el monto que transferiste en bolívares.',
            'amount_bs.numeric' => 'El monto debe ser un número.',
            'amount_bs.min' => 'El monto debe ser mayor que cero.',
            'bank.required' => 'Indica el banco desde el que hiciste la transferencia.',
            'reference.required' => 'Escribe el número de referencia de la transferencia.',
            'reference.min' => 'La referencia debe tener al menos 3 caracteres.',
            'transferred_at.required' => 'Indica la fecha de la transferencia.',
            'transferred_at.before_or_equal' => 'La fecha de la transferencia no puede estar en el futuro.',
            'comprobante.required' => 'Adjunta la foto o el PDF del comprobante.',
            'comprobante.mimes' => 'El comprobante debe ser JPG, PNG, WEBP o PDF.',
            'comprobante.max' => 'El comprobante no puede pesar más de 6 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_bs' => 'monto en bolívares',
            'bank' => 'banco',
            'reference' => 'referencia',
            'transferred_at' => 'fecha de la transferencia',
            'comprobante' => 'comprobante',
        ];
    }
}
