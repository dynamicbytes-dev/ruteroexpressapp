<?php

namespace App\Domain\Verification\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VerificationReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor rechaza la cuenta completa (no un documento puntual): por ejemplo
 * cuando los datos del vehículo no coinciden con el carnet de circulación.
 *
 * La persona puede corregir y volver a enviar; el motivo es obligatorio.
 */
class RechazarCuenta
{
    public function ejecutar(User $usuario, User $auditor, string $motivo): User
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'reason' => 'Explica el motivo del rechazo: la persona lo va a leer.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $auditor, $motivo): User {
            $usuario->forceFill([
                'status' => UserStatus::Rejected,
                'status_reason' => $motivo,
                'approved_at' => null,
                'submitted_at' => null,
            ])->save();

            VerificationReview::registrar([
                'user_id' => $usuario->id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_ACCOUNT,
                'decision' => VerificationReview::DECISION_REJECTED,
                'reason' => $motivo,
                'ip_address' => request()->ip(),
            ]);

            return $usuario->refresh();
        });
    }
}
