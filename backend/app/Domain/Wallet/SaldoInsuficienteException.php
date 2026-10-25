<?php

namespace App\Domain\Wallet;

use RuntimeException;

/**
 * La billetera no tiene saldo disponible para la operación.
 *
 * El mensaje está escrito para mostrarse tal cual en la app.
 */
class SaldoInsuficienteException extends RuntimeException
{
    public static function paraRetirar(float $disponible, float $solicitado): self
    {
        return new self(sprintf(
            'No tienes saldo suficiente: disponible $%s y solicitaste $%s.',
            number_format($disponible, 2, '.', ','),
            number_format($solicitado, 2, '.', ','),
        ));
    }

    public static function paraOperar(float $disponible, float $necesario): self
    {
        return new self(sprintf(
            'Saldo insuficiente: necesitas $%s y tienes $%s disponibles.',
            number_format($necesario, 2, '.', ','),
            number_format($disponible, 2, '.', ','),
        ));
    }
}
