<?php

namespace App\Domain\Verification\Actions;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor aprueba un documento.
 *
 * Se guarda quién lo revisó y cuándo, y queda la constancia inmutable en
 * `verification_reviews`. La cuenta sigue esperando: recién se activa con la
 * aprobación de la cuenta completa.
 */
class AprobarDocumento
{
    public function ejecutar(UserDocument $documento, User $auditor): UserDocument
    {
        if ($documento->isExpired()) {
            throw ValidationException::withMessages([
                'document' => 'Este documento está vencido. La persona debe subir uno vigente antes de aprobarlo.',
            ]);
        }

        return DB::transaction(function () use ($documento, $auditor): UserDocument {
            $documento->update([
                'status' => DocumentStatus::Approved,
                'reviewed_by' => $auditor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            VerificationReview::registrar([
                'user_id' => $documento->user_id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_DOCUMENT,
                'document_id' => $documento->id,
                'document_type' => $documento->type,
                'document_path' => $documento->file_path,
                'decision' => VerificationReview::DECISION_APPROVED,
                'reason' => null,
                'ip_address' => request()->ip(),
            ]);

            return $documento->refresh();
        });
    }
}
