<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\WalletService;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** El auditor rechaza un retiro: se libera el monto retenido. */
class RechazarRetiro
{
    public function __construct(private readonly WalletService $billetera) {}

    public function ejecutar(Withdrawal $retiro, User $auditor, string $motivo): Withdrawal
    {
        return DB::transaction(function () use ($retiro, $auditor, $motivo): Withdrawal {
            /** @var Withdrawal $retiro */
            $retiro = Withdrawal::query()->whereKey($retiro->getKey())->lockForUpdate()->firstOrFail();

            if ($retiro->status !== WithdrawalStatus::Pending) {
                throw new RuntimeException('Este retiro ya fue procesado.');
            }

            $this->billetera->liberarRetencion(
                $this->billetera->paraUsuario($retiro->user),
                (float) $retiro->amount_usd,
            );

            $retiro->update([
                'status' => WithdrawalStatus::Rejected,
                'reviewed_by' => $auditor->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => trim($motivo),
            ]);

            return $retiro->refresh();
        });
    }
}
