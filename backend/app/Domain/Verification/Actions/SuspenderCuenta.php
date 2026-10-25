<?php

namespace App\Domain\Verification\Actions;

use App\Domain\Trips\TripDispatcher;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\VerificationReview;
use App\Notifications\CuentaInhabilitada;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El auditor suspende manualmente una cuenta aprobada.
 *
 * La persona deja de poder operar y, si es conductor, se apaga su
 * disponibilidad y se cancelan las alertas pendientes. El motivo es
 * obligatorio y queda en el historial.
 */
class SuspenderCuenta
{
    public function __construct(private readonly TripDispatcher $asignador) {}

    public function ejecutar(User $usuario, User $auditor, string $motivo): User
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'reason' => 'Explica el motivo de la suspensión: la persona lo va a leer.',
            ]);
        }

        if ($usuario->status !== UserStatus::Approved) {
            throw ValidationException::withMessages([
                'account' => 'Solo se puede suspender una cuenta que esté aprobada.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $auditor, $motivo): User {
            $usuario->forceFill([
                'status' => UserStatus::Suspended,
                'status_reason' => $motivo,
                'submitted_at' => null,
            ])->save();

            if ($usuario->isDriver() && $usuario->driverProfile !== null) {
                $usuario->driverProfile->forceFill([
                    'is_online' => false,
                    'went_online_at' => null,
                ])->save();

                $this->asignador->cancelarOfertaPendienteDeConductor($usuario);
                $usuario->notify(new CuentaInhabilitada($motivo));
            }

            VerificationReview::registrar([
                'user_id' => $usuario->id,
                'auditor_id' => $auditor->id,
                'scope' => VerificationReview::SCOPE_ACCOUNT,
                'decision' => VerificationReview::DECISION_SUSPENDED,
                'reason' => $motivo,
                'ip_address' => request()->ip(),
            ]);

            return $usuario->refresh();
        });
    }
}
