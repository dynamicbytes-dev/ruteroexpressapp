<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación de una persona para la app móvil.
 *
 * Nunca expone la contraseña, el token ni las rutas internas de los archivos.
 * Los documentos se describen por estado; la única imagen que se enlaza es la
 * selfie vigente, con una URL firmada para usarla como foto de perfil.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $documents = $this->relationLoaded('documents')
            ? $this->documents
            : $this->documents()->get();

        // La ficha y la URL del avatar leen la misma colección.
        $this->resource->setRelation('documents', $documents);

        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),

            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->name,
            'national_id' => $this->national_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city === null ? null : [
                'id' => $this->city->id,
                'name' => $this->city->name,
                'state' => $this->city->state,
                'display_name' => $this->city->displayName(),
            ],

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_reason' => $this->status_reason,
            'can_operate' => $this->canOperate(),

            'avatar' => $this->avatarUrl() ?? $this->photoUrl(),
            'photo_url' => $this->avatarUrl() ?? $this->photoUrl(),

            'email_verified' => $this->hasVerifiedEmail(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'verification' => $this->verificationSummary(),

            'driver_profile' => $this->when(
                $this->isDriver() && $this->driverProfile !== null,
                fn (): array => [
                    'vehicle_type' => $this->driverProfile->vehicle_type?->value,
                    'vehicle_type_label' => $this->driverProfile->vehicleTypeLabel(),
                    'vehicle_brand' => $this->driverProfile->vehicle_brand,
                    'vehicle_model' => $this->driverProfile->vehicle_model,
                    'vehicle_year' => $this->driverProfile->vehicle_year,
                    'vehicle_plate' => $this->driverProfile->vehicle_plate,
                    'vehicle_description' => $this->driverProfile->vehicleDescription(),
                    'is_online' => $this->driverProfile->is_online,
                    'city' => $this->driverProfile->city === null ? null : [
                        'id' => $this->driverProfile->city->id,
                        'name' => $this->driverProfile->city->name,
                        'state' => $this->driverProfile->city->state,
                    ],
                ],
            ),
        ];
    }

    /**
     * URL firmada de la selfie vigente, solo si es una imagen.
     *
     * La app la usa como foto de perfil. Si la persona subió un PDF o todavía
     * no tiene selfie, el campo queda en null y la app muestra sus iniciales.
     */
    private function photoUrl(): ?string
    {
        $selfie = $this->documents->first(
            fn (UserDocument $document): bool => $document->type === DocumentType::Selfie,
        );

        if ($selfie === null || ! str_starts_with((string) $selfie->mime_type, 'image/')) {
            return null;
        }

        return $selfie->avatarUrl();
    }

    /**
     * Lista de verificación documento por documento, en el orden en que la app
     * debe mostrarlos, más los que todavía faltan subir.
     *
     * @return array<string, mixed>
     */
    private function verificationSummary(): array
    {
        $documents = $this->relationLoaded('documents')
            ? $this->documents->keyBy(fn (UserDocument $document): string => $document->type->value)
            : $this->documents()->get()->keyBy(fn (UserDocument $document): string => $document->type->value);

        $items = [];
        $missing = [];

        foreach ($this->role->requiredDocuments() as $tipo) {
            $documento = $documents->get($tipo->value);

            if ($documento === null) {
                $missing[] = $tipo->label();

                $items[] = [
                    'type' => $tipo->value,
                    'label' => $tipo->label(),
                    'hint' => $tipo->hint(),
                    'uploaded' => false,
                    'status' => null,
                    'status_label' => 'Falta subirlo',
                    'rejection_reason' => null,
                    'uploaded_at' => null,
                    'expires_at' => null,
                    'expired' => false,
                ];

                continue;
            }

            $items[] = [
                'type' => $tipo->value,
                'label' => $tipo->label(),
                'hint' => $tipo->hint(),
                'uploaded' => true,
                'status' => $documento->status->value,
                'status_label' => $documento->status->label(),
                'rejection_reason' => $documento->rejection_reason,
                'uploaded_at' => $documento->uploaded_at?->toIso8601String(),
                'expires_at' => $documento->expires_at?->toDateString(),
                'expired' => $documento->isExpired(),
            ];
        }

        return array_merge($this->documentsSummary(), [
            'missing_labels' => $missing,
            'items' => $items,
        ]);
    }
}
