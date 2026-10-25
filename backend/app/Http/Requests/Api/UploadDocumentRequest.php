<?php

namespace App\Http\Requests\Api;

use App\Enums\DocumentType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    /**
     * La autorización va antes de las reglas a propósito: si el documento no
     * corresponde al rol, la persona debe recibir un 403 claro y no un error de
     * "falta la fecha de vencimiento" que no viene al caso.
     */
    public function authorize(): bool
    {
        $tipo = DocumentType::tryFrom((string) $this->route('tipo'));

        if ($tipo === null) {
            // El controlador responde 404 con el tipo inexistente.
            return true;
        }

        if (! in_array($tipo, $this->user()->role->requiredDocuments(), true)) {
            throw new AuthorizationException(
                "El documento «{$tipo->label()}» no corresponde a tu tipo de cuenta."
            );
        }

        return true;
    }

    public function rules(): array
    {
        $tipo = DocumentType::tryFrom((string) $this->route('tipo'));
        $esSelfie = $tipo === DocumentType::Selfie;

        return [
            'archivo' => [
                'required',
                'file',
                $esSelfie ? 'image' : 'file',
                'mimes:'.($esSelfie ? 'jpg,jpeg,png,webp' : DocumentType::acceptedMimeTypes()),
                'max:'.DocumentType::maxSizeKb(),
            ],
            // Solo se pide la fecha de vencimiento en documentos que vencen.
            'vence_el' => [
                Rule::requiredIf($tipo?->expires() ?? false),
                'nullable',
                'date',
                'after:today',
            ],
        ];
    }

    public function messages(): array
    {
        $tipo = DocumentType::tryFrom((string) $this->route('tipo'));
        $esSelfie = $tipo === DocumentType::Selfie;

        return [
            'archivo.required' => 'Adjunta la foto o el archivo del documento.',
            'archivo.image' => 'La fotografía (selfie) debe ser una imagen válida (JPG o PNG).',
            'archivo.mimes' => $esSelfie
                ? 'La selfie debe ser una foto en formato JPG o PNG.'
                : 'Solo se aceptan fotos (JPG, PNG, WEBP) o PDF.',
            'archivo.max' => 'El archivo no puede pesar más de 6 MB.',
            'vence_el.required' => 'Indica la fecha de vencimiento del documento.',
            'vence_el.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'documento',
            'vence_el' => 'fecha de vencimiento',
        ];
    }
}
