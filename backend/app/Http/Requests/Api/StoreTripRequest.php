<?php

namespace App\Http\Requests\Api;

class StoreTripRequest extends QuoteTripRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'origin_address' => ['required', 'string', 'max:255'],
            'destination_address' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'in:wallet,cash'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'origin_address.required' => 'Escribe el punto de recogida.',
            'destination_address.required' => 'Escribe el destino.',
            'payment_method.required' => 'Elige cómo vas a pagar el viaje.',
            'payment_method.in' => 'El método de pago no es válido.',
        ]);
    }
}
