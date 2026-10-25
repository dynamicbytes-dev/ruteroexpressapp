<?php

namespace App\Domain\Wallet\Actions;

use App\Enums\RechargeStatus;
use App\Models\Recharge;
use App\Models\User;
use RuntimeException;

/** El auditor rechaza una recarga: no se acredita nada. */
class RechazarRecarga
{
    public function ejecutar(Recharge $recarga, User $auditor, string $motivo): Recharge
    {
        if ($recarga->status !== RechargeStatus::Pending) {
            throw new RuntimeException('Esta recarga ya fue revisada.');
        }

        $recarga->update([
            'status' => RechargeStatus::Rejected,
            'reviewed_by' => $auditor->getKey(),
            'reviewed_at' => now(),
            'rejection_reason' => trim($motivo),
        ]);

        return $recarga->refresh();
    }
}
