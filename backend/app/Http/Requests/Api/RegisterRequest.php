<?php

namespace App\Http\Requests\Api;

use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\VehicleType;
use App\Http\Requests\Concerns\NormalizaDatosPersonales;
use App\Rules\DocumentoDelRol;
use App\Rules\GmailOnlyEmail;
use App\Rules\TelefonoVe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Registro desde la app móvil.
 *
 * Un solo formulario, pero con reglas condicionales según el rol: el conductor
 * además elige el tipo de vehículo (moto o carro) y carga marca, modelo, año y
 * placa. Los documentos son opcionales aquí
 * (la app puede subirlos después con POST /api/documentos/{tipo}) y la cuenta
 * entra a la cola del auditor cuando están todos.
 */
class RegisterRequest extends FormRequest
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
        $esConductor = $this->input('role') === UserRole::Driver->value;

        return [
            'role' => ['required', Rule::in(array_map(
                static fn (UserRole $rol): string => $rol->value,
                UserRole::selfRegistrable(),
            ))],

            // Datos de la persona
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'national_id' => [
                'required',
                'digits_between:6,9',
                Rule::unique('users', 'national_id')
                    ->where(fn ($query) => $query->where('role', (string) $this->input('role'))),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('role', (string) $this->input('role'))),
                new GmailOnlyEmail,
            ],
            'phone' => ['required', 'string', new TelefonoVe],
            'address' => ['required', 'string', 'min:10', 'max:255'],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where('is_active', true),
            ],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Aceptación de los documentos legales vigentes. La versión evita
            // que una app vieja acepte un texto que ya cambió.
            'accept_terms' => ['required', 'accepted'],
            'terms_version' => [
                'required',
                'string',
                Rule::in([(string) config('legal.terms_version')]),
            ],

            // Datos del vehículo (solo conductor)
            'vehicle_type' => [
                Rule::requiredIf($esConductor),
                Rule::in(VehicleType::values()),
            ],
            'vehicle_brand' => [Rule::requiredIf($esConductor), 'string', 'min:2', 'max:60'],
            'vehicle_model' => [Rule::requiredIf($esConductor), 'string', 'min:1', 'max:60'],
            'vehicle_year' => [Rule::requiredIf($esConductor), 'integer', 'min:1990', 'max:'.(date('Y') + 1)],
            'vehicle_plate' => [
                Rule::requiredIf($esConductor),
                'string',
                'max:12',
                Rule::unique('driver_profiles', 'vehicle_plate'),
            ],

            // Foto de perfil o avatar directa
            'avatar' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.DocumentType::maxSizeKb()],
            'photo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.DocumentType::maxSizeKb()],

            // Documentos opcionales, en una sola petición multipart
            'documentos' => ['sometimes', 'array', new DocumentoDelRol((string) $this->input('role'))],
            'documentos.selfie' => [
                'sometimes',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.DocumentType::maxSizeKb(),
            ],
            'documentos.*' => [
                'file',
                'mimes:'.DocumentType::acceptedMimeTypes(),
                'max:'.DocumentType::maxSizeKb(),
            ],
            'documentos_expiracion' => ['sometimes', 'array'],
            'documentos_expiracion.*' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Solo puedes registrarte como pasajero o conductor.',
            'email.unique' => 'Ya tienes una cuenta de este tipo con ese correo.',
            'national_id.unique' => 'Ya tienes una cuenta de ese tipo con esta cédula.',
            'national_id.digits_between' => 'La cédula debe tener entre 6 y 9 dígitos, sin puntos.',
            'city_id.required' => 'Elige la ciudad donde vas a usar GAZ.',
            'city_id.exists' => 'Esa ciudad no está disponible. Actualiza la app y elige otra.',
            'vehicle_type.required' => 'Indica si vas a usar moto o carro.',
            'vehicle_type.in' => 'El tipo de vehículo debe ser moto o carro.',
            'vehicle_plate.unique' => 'Esa placa ya está registrada por otro conductor.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'accept_terms.required' => 'Debes aceptar los términos y condiciones.',
            'accept_terms.accepted' => 'Debes aceptar los términos y condiciones.',
            'terms_version.required' => 'No se pudo verificar la versión de los términos. Actualiza la app.',
            'terms_version.in' => 'Los términos cambiaron. Actualiza la app para continuar.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'national_id' => 'cédula',
            'email' => 'correo',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'city_id' => 'ciudad',
            'accept_terms' => 'aceptación de términos',
            'terms_version' => 'versión de los términos',
            'vehicle_type' => 'tipo de vehículo',
            'vehicle_brand' => 'marca del vehículo',
            'vehicle_model' => 'modelo del vehículo',
            'vehicle_year' => 'año del vehículo',
            'vehicle_plate' => 'placa',
        ];
    }
}
