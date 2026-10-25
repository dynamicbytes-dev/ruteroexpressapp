<?php

namespace App\Domain\Verification\Actions;

use App\Enums\DocumentStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor rechaza un documento.
 *
 * El motivo es obligatorio y le llega a la persona: sin una explicación clara
 * no puede saber qué corregir. La cuenta pasa a "rechazado" y sale de la cola
 * hasta que vuelva a subir lo que falta.
 */
class RechazarDocumento
{
    public function ejecutar(UserDocument $documento, User $auditor, string $motivo): UserDocument
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'reason' => 'Explica el motivo del rechazo: la persona lo va a leer.',
            ]);
        }

        return DB::transaction(function () use ($documento, $auditor, $motivo): UserDocument {
            $documento->update([
                'status' => DocumentStatus::Rejected,
                'reviewed_by' => $auditor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $motivo,
            ]);

            $usuario = $documento->user;

            $usuario->forceFill([
                'status' => UserStatus::Rejected,
                'status_reason' => "Documento rechazado · {$documento->type->label()}: {$motivo}",
                // Sale de la cola: todavía no hay nada que aprobar.
                'submitted_at' => null,
                'approved_at' => null,
            ])->save();

            VerificationReview::registrar([
                'user_id' => $documento->user_id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_DOCUMENT,
                'document_id' => $documento->id,
                'document_type' => $documento->type,
                'document_path' => $documento->file_path,
                'decision' => VerificationReview::DECISION_REJECTED,
                'reason' => $motivo,
                'ip_address' => request()->ip(),
            ]);

            return $documento->refresh();
        });
    }
}
