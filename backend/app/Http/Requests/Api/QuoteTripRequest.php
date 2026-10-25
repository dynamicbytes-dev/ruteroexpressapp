<?php

namespace App\Http\Requests\Api;

use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las versiones anteriores de la app no enviaban `vehicle_type`; se asume
     * moto para no romperlas. La app nueva siempre lo envía.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('vehicle_type') || $this->input('vehicle_type') === null) {
            $this->merge(['vehicle_type' => VehicleType::Motorcycle->value]);
        }
    }

    public function rules(): array
    {
        return [
            'origin_lat' => ['required', 'numeric', 'between:-90,90'],
            'origin_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'origin_address' => ['nullable', 'string', 'max:255'],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['required', Rule::in(VehicleType::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'origin_lat.required' => 'Falta la ubicación de origen.',
            'origin_lng.required' => 'Falta la ubicación de origen.',
            'destination_lat.required' => 'Falta la ubicación de destino.',
            'destination_lng.required' => 'Falta la ubicación de destino.',
            'origin_lat.between' => 'La ubicación de origen no es válida.',
            'origin_lng.between' => 'La ubicación de origen no es válida.',
            'destination_lat.between' => 'La ubicación de destino no es válida.',
            'destination_lng.between' => 'La ubicación de destino no es válida.',
            'vehicle_type.required' => 'Indica si el viaje es en moto o en carro.',
            'vehicle_type.in' => 'El tipo de vehículo debe ser moto o carro.',
        ];
    }

    public function attributes(): array
    {
        return [
            'vehicle_type' => 'tipo de vehículo',
        ];
    }
}
