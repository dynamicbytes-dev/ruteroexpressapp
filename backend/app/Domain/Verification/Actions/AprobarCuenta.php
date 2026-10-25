<?php

namespace App\Domain\Verification\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor activa la cuenta.
 *
 * Solo se puede aprobar cuando todos los documentos del rol están aprobados:
 * así no queda operando gente con una foto pendiente o vencida.
 */
class AprobarCuenta
{
    public function ejecutar(User $usuario, User $auditor): User
    {
        if (! $usuario->canBeApproved()) {
            throw ValidationException::withMessages([
                'account' => 'Todavía no puedes aprobar esta cuenta: hay documentos sin aprobar o sin subir.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $auditor): User {
            $usuario->forceFill([
                'status' => UserStatus::Approved,
                'status_reason' => null,
                'approved_at' => now(),
                'submitted_at' => null,
            ])->save();

            VerificationReview::registrar([
                'user_id' => $usuario->id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_ACCOUNT,
                'decision' => VerificationReview::DECISION_APPROVED,
                'reason' => null,
                'ip_address' => request()->ip(),
            ]);

            return $usuario->refresh();
        });
    }
}
