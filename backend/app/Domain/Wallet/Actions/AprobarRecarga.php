<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\WalletService;
use App\Enums\RechargeStatus;
use App\Enums\WalletTransactionType;
use App\Models\ExchangeRate;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El auditor aprueba una recarga: se calcula el monto en dólares con la tasa
 * vigente (o con la que el auditor escriba en ese momento) y se acredita.
 */
class AprobarRecarga
{
    public function __construct(private readonly WalletService $billetera) {}

    public function ejecutar(Recharge $recarga, User $auditor, ?float $tasa = null): Recharge
    {
        return DB::transaction(function () use ($recarga, $auditor, $tasa): Recharge {
            /** @var Recharge $recarga */
            $recarga = Recharge::query()->whereKey($recarga->getKey())->lockForUpdate()->firstOrFail();

            if ($recarga->status !== RechargeStatus::Pending) {
                throw new RuntimeException('Esta recarga ya fue revisada.');
            }

            $tasa ??= (float) (ExchangeRate::vigente()?->rate ?? 0);

            if ($tasa <= 0) {
                throw new RuntimeException(
                    'No hay una tasa de cambio vigente. Carga la tasa del día antes de aprobar.',
                );
            }

            $montoUsd = round((float) $recarga->amount_bs / $tasa, 2);

            if ($montoUsd <= 0) {
                throw new RuntimeException('El monto de la recarga es demasiado pequeño para convertirlo.');
            }

            $recarga->update([
                'status' => RechargeStatus::Approved,
                'exchange_rate' => $tasa,
                'amount_usd' => $montoUsd,
                'reviewed_by' => $auditor->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->billetera->acreditar(
                $this->billetera->paraUsuario($recarga->user),
                $montoUsd,
                WalletTransactionType::Recharge,
                'Recarga aprobada · Bs '.number_format((float) $recarga->amount_bs, 2, ',', '.')." a tasa {$tasa}",
                $recarga,
                $auditor,
                ['tasa' => $tasa, 'monto_bs' => (float) $recarga->amount_bs],
            );

            return $recarga->refresh();
        });
    }
}
