<?php

namespace App\Domain\Verification\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor devuelve una cuenta suspendida al estado aprobado.
 *
 * No hace falta volver a revisar documentos: la suspensión fue una decisión
 * manual y la reactivación también. Queda registrada en el historial.
 */
class ReactivarCuenta
{
    public function ejecutar(User $usuario, User $auditor): User
    {
        if ($usuario->status !== UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'account' => 'Solo se puede reactivar una cuenta suspendida.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $auditor): User {
            $usuario->forceFill([
                'status' => UserStatus::Approved,
                'status_reason' => null,
                'submitted_at' => null,
                'approved_at' => $usuario->approved_at ?? now(),
            ])->save();

            VerificationReview::registrar([
                'user_id' => $usuario->id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_ACCOUNT,
                'decision' => VerificationReview::DECISION_REACTIVATED,
                'reason' => null,
                'ip_address' => request()->ip(),
            ]);

            return $usuario->refresh();
        });
    }
}
