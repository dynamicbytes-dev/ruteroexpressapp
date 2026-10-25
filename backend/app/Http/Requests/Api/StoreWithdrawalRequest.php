<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'bank' => ['required', 'string', 'min:2', 'max:80'],
            'account_number' => ['required', 'string', 'min:4', 'max:40'],
            'account_holder' => ['required', 'string', 'min:3', 'max:120'],
            'account_id' => ['nullable', 'string', 'digits_between:6,9'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_usd.required' => 'Indica cuánto quieres retirar.',
            'amount_usd.numeric' => 'El monto debe ser un número.',
            'amount_usd.min' => 'El monto debe ser mayor que cero.',
            'bank.required' => 'Indica el banco de la cuenta destino.',
            'account_number.required' => 'Escribe el número de cuenta.',
            'account_holder.required' => 'Escribe el nombre del titular de la cuenta.',
            'account_id.digits_between' => 'La cédula del titular debe tener entre 6 y 9 dígitos.',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_usd' => 'monto',
            'bank' => 'banco',
            'account_number' => 'número de cuenta',
            'account_holder' => 'titular',
            'account_id' => 'cédula del titular',
        ];
    }
}
