<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Notifications\NotificarAuditores;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda (o reemplaza) el documento de un usuario en el disco privado.
 *
 * Reglas:
 * - Solo se aceptan los tipos que corresponden al rol.
 * - Hay un único documento vigente por tipo: si se reenvía, se reemplaza y el
 *   archivo anterior se borra del disco.
 * - Todo documento nuevo vuelve a estado "en revisión": el auditor tiene que
 *   mirarlo otra vez.
 */
class GuardarDocumento
{
    public function __construct(private readonly NotificarAuditores $notificar) {}

    public function ejecutar(
        User $usuario,
        DocumentType $tipo,
        UploadedFile $archivo,
        ?string $venceEl = null,
    ): UserDocument {
        if (! in_array($tipo, $usuario->role->requiredDocuments(), true)) {
            throw new \InvalidArgumentException(
                "El documento «{$tipo->label()}» no corresponde a un usuario {$usuario->role->label()}."
            );
        }

        if ($tipo === DocumentType::Selfie) {
            $mime = (string) $archivo->getMimeType();
            $ext = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension() ?: '');
            if (! str_starts_with($mime, 'image/') || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw new \InvalidArgumentException('La fotografía (selfie) debe ser una imagen válida (JPG o PNG).');
            }
        }

        $disco = (string) config('motoruta.documents.disk', 'local');
        $carpeta = trim((string) config('motoruta.documents.directory', 'documentos'), '/')."/{$usuario->id}";

        $nombre = sprintf(
            '%s-%s.%s',
            $tipo->value,
            Str::lower(Str::ulid()->toBase32()),
            $archivo->getClientOriginalExtension() ?: $archivo->extension() ?: 'jpg',
        );

        $ruta = $archivo->storeAs($carpeta, $nombre, ['disk' => $disco]);

        // Si es selfie, guardar también en el disco público y actualizar la columna avatar de users
        if ($tipo === DocumentType::Selfie) {
            $avatarDisk = (string) config('motoruta.avatar_disk', 'public');
            $avatarFolder = "avatars/{$usuario->id}";
            $avatarNombre = sprintf(
                'avatar-%s.%s',
                Str::lower(Str::ulid()->toBase32()),
                $archivo->getClientOriginalExtension() ?: $archivo->extension() ?: 'jpg',
            );
            $avatarRuta = $archivo->storeAs($avatarFolder, $avatarNombre, ['disk' => $avatarDisk]);

            if ($usuario->avatar && $usuario->avatar !== $avatarRuta && Storage::disk($avatarDisk)->exists($usuario->avatar)) {
                Storage::disk($avatarDisk)->delete($usuario->avatar);
            }

            $usuario->forceFill(['avatar' => $avatarRuta])->save();
        }

        /** @var UserDocument|null $anterior */
        $anterior = $usuario->documents()->where('type', $tipo)->first();

        $documento = $usuario->documents()->updateOrCreate(
            ['type' => $tipo],
            [
                'status' => DocumentStatus::Pending,
                'file_path' => $ruta,
                'disk' => $disco,
                'original_name' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'size_bytes' => $archivo->getSize(),
                'uploaded_at' => now(),
                'expires_at' => $tipo->expires() ? $venceEl : null,
                // Un documento nuevo vuelve a empezar la cuenta de avisos.
                'expiry_notice_days' => null,
                // Al reenviar se limpia la revisión anterior: se vuelve a evaluar.
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ],
        );

        // El archivo reemplazado ya no hace falta.
        if ($anterior !== null && $anterior->file_path !== $ruta) {
            Storage::disk($anterior->disk)->delete($anterior->file_path);
        }

        $estabaEnCola = $usuario->isInReviewQueue();
        $esCorreccion = $usuario->status === UserStatus::Rejected;
        $estabaAprobado = $usuario->status === UserStatus::Approved;

        $usuario->actualizarEnvioARevision();

        $sigueEnCola = $usuario->isInReviewQueue();

        if (! $estabaEnCola && $sigueEnCola) {
            // La cuenta acaba de completar (o corregir) todos sus documentos.
            $this->notificar->ejecutar(
                $esCorreccion ? 'cuenta_corregida' : 'cuenta_por_verificar',
                $esCorreccion ? 'Cuenta corregida por revisar' : 'Nueva cuenta por verificar',
                "{$usuario->name} ({$usuario->role->label()}) terminó de subir sus documentos.",
                route('panel.usuarios.show', $usuario),
                ['user_id' => $usuario->id, 'role' => $usuario->role->value],
            );
        } elseif ($estabaEnCola || $estabaAprobado) {
            // Reemplazos en una cuenta que ya estaba en cola y renovaciones de
            // cuentas aprobadas: el auditor debe revisar este documento aunque
            // la cuenta no cambie de estado.
            $this->notificar->ejecutar(
                'documento_por_verificar',
                'Documento por revisar',
                "{$usuario->name} ({$usuario->role->label()}) subió «{$tipo->label()}» y requiere revisión.",
                route('panel.usuarios.show', $usuario),
                [
                    'user_id' => $usuario->id,
                    'document_id' => $documento->id,
                    'document_type' => $tipo->value,
                ],
            );
        }

        return $documento;
    }
}
