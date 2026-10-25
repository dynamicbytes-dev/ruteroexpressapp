<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Notifications\NotificarAuditores;
use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Domain\Wallet\WalletService;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Configuracion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El conductor pide un retiro. El monto queda retenido de inmediato para que
 * no lo pueda gastar mientras el auditor lo procesa.
 */
class SolicitarRetiro
{
    public function __construct(
        private readonly WalletService $billetera,
        private readonly NotificarAuditores $notificar,
    ) {}

    /**
     * @param  array{amount_usd: float|string, bank: string, account_number: string, account_holder: string, account_id?: string|null}  $datos
     */
    public function ejecutar(User $conductor, array $datos): Withdrawal
    {
        if (! $conductor->isDriver()) {
            throw new OperacionNoPermitidaException('Solo los conductores pueden retirar ganancias.');
        }

        if (! $conductor->canOperate()) {
            throw new OperacionNoPermitidaException(
                'Tu cuenta no está habilitada para retirar en este momento.',
            );
        }

        $monto = round((float) $datos['amount_usd'], 2);
        $minimo = Configuracion::decimal('retiro_minimo_usd', 1.0);

        if ($monto < $minimo) {
            throw new RuntimeException(sprintf(
                'El retiro mínimo es de $%s.',
                number_format($minimo, 2, '.', ','),
            ));
        }

        return DB::transaction(function () use ($conductor, $datos, $monto): Withdrawal {
            $billetera = $this->billetera->paraUsuario($conductor);

            $retiro = Withdrawal::create([
                'user_id' => $conductor->getKey(),
                'amount_usd' => $monto,
                'bank' => $datos['bank'],
                'account_number' => trim($datos['account_number']),
                'account_holder' => $datos['account_holder'],
                'account_id' => $datos['account_id'] ?? null,
                'status' => WithdrawalStatus::Pending,
            ]);

            // Si no hay saldo, la excepción revierte también el retiro.
            $this->billetera->retener($billetera, $monto);

            $this->notificar->ejecutar(
                'retiro_pendiente',
                'Nuevo retiro por procesar',
                "{$conductor->name} solicitó un retiro de $".number_format($monto, 2, '.', ',')." ({$retiro->bank}).",
                route('panel.retiros.show', $retiro),
                ['withdrawal_id' => $retiro->id],
            );

            return $retiro;
        });
    }
}
