<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\WalletService;
use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\WalletTransaction;
use InvalidArgumentException;

/**
 * Corrección manual del auditor. La nota es obligatoria para que el libro
 * mayor explique siempre por qué se movió el saldo.
 */
class AjustarSaldo
{
    public function __construct(private readonly WalletService $billetera) {}

    public function ejecutar(User $usuario, float $monto, string $nota, User $auditor): WalletTransaction
    {
        $nota = trim($nota);

        if ($nota === '') {
            throw new InvalidArgumentException('El ajuste necesita una nota que explique el motivo.');
        }

        $monto = round($monto, 2);

        if ($monto === 0.0) {
            throw new InvalidArgumentException('El ajuste no puede ser de cero dólares.');
        }

        $billetera = $this->billetera->paraUsuario($usuario);
        $descripcion = "Ajuste manual · {$nota}";

        $movimiento = $monto > 0
            ? $this->billetera->acreditar(
                $billetera,
                $monto,
                WalletTransactionType::Adjustment,
                $descripcion,
                $usuario,
                $auditor,
                ['nota' => $nota],
            )
            : $this->billetera->debitar(
                $billetera,
                abs($monto),
                WalletTransactionType::Adjustment,
                $descripcion,
                $usuario,
                $auditor,
                ['nota' => $nota],
                piso: -999999999.99,
            );

        activity('billetera')
            ->performedOn($usuario)
            ->causedBy($auditor)
            ->withProperties([
                'amount' => $monto,
                'note' => $nota,
                'transaction_id' => $movimiento->getKey(),
                'balance_after' => (float) $movimiento->balance_after,
                'ip_address' => request()->ip(),
            ])
            ->log('Ajuste manual de saldo');

        return $movimiento;
    }
}
