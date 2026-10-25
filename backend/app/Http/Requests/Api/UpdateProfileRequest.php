<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizaDatosPersonales;
use App\Rules\GmailOnlyEmail;
use App\Rules\TelefonoVe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de los datos personales desde la app.
 *
 * La cédula no se puede cambiar: es la llave de identidad y solo el auditor
 * puede corregirla con un motivo y registro de auditoría. El correo sí puede
 * cambiar, pero exige la contraseña actual y vuelve a quedar sin verificar.
 */
class UpdateProfileRequest extends FormRequest
{
    use NormalizaDatosPersonales;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizarPersonales();
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'last_name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'phone' => ['sometimes', 'string', new TelefonoVe],
            'address' => ['sometimes', 'string', 'min:8', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'lowercase',
                'email',
                'max:255',
                new GmailOnlyEmail,
                Rule::unique('users', 'email')
                    ->ignore($this->user()->getKey())
                    ->where(fn ($query) => $query->where('role', $this->user()->role->value)),
            ],
            'current_password' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => $this->correoCambia()),
                'current_password:sanctum',
            ],
            'avatar' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'photo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'selfie' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'national_id' => ['prohibited'],
            'role' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => 'El avatar debe ser una imagen válida (JPG o PNG).',
            'avatar.mimes' => 'El avatar debe estar en formato JPG o PNG.',
            'photo.image' => 'La foto de perfil debe ser una imagen válida (JPG o PNG).',
            'photo.mimes' => 'La foto de perfil debe estar en formato JPG o PNG.',
            'selfie.image' => 'La selfie debe ser una imagen válida (JPG o PNG).',
            'selfie.mimes' => 'La selfie debe estar en formato JPG o PNG.',
            'email.unique' => 'Ya tienes una cuenta de este tipo con ese correo.',
            'national_id.prohibited' => 'La cédula no se puede editar desde el perfil.',
            'role.prohibited' => 'El rol no se puede cambiar desde el perfil.',
            'status.prohibited' => 'El estado de la cuenta no se puede cambiar desde el perfil.',
            'current_password.required' => 'Escribe tu contraseña actual para cambiar el correo.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'email' => 'correo',
        ];
    }

    private function correoCambia(): bool
    {
        if (! $this->filled('email')) {
            return false;
        }

        return mb_strtolower((string) $this->input('email')) !== mb_strtolower((string) $this->user()->email);
    }
}
