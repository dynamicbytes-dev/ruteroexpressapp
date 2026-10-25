<?php

namespace App\Domain\Wallet;

use App\Enums\WalletTransactionType;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar que mueve dinero.
 *
 * Reglas:
 * - Todo cambio queda en `wallet_transactions` (libro de solo-inserción).
 * - Los saldos se bloquean con `lockForUpdate` para que dos operaciones
 *   simultáneas no puedan gastar el mismo dinero.
 * - Los montos siempre se redondean al centavo.
 */
class WalletService
{
    /** Devuelve la billetera de la persona, creándola si todavía no existe. */
    public function paraUsuario(User $usuario): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $usuario->getKey()]);
    }

    /** Saldo que se puede gastar en este momento (descuenta lo retenido). */
    public function disponible(Wallet $billetera): float
    {
        return round((float) $billetera->balance - (float) $billetera->held_balance, 2);
    }

    public function acreditar(
        Wallet $billetera,
        float $monto,
        WalletTransactionType $tipo,
        string $descripcion,
        ?Model $referencia = null,
        ?User $autor = null,
        array $metadata = [],
    ): WalletTransaction {
        $monto = round(abs($monto), 2);

        if ($monto <= 0) {
            throw new \InvalidArgumentException('El monto a acreditar debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($billetera, $monto, $tipo, $descripcion, $referencia, $autor, $metadata): WalletTransaction {
            $billetera = $this->bloquear($billetera);
            $billetera->balance = round((float) $billetera->balance + $monto, 2);
            $billetera->save();

            return $this->registrar($billetera, $tipo, 'credit', $monto, $descripcion, $referencia, $autor, $metadata);
        });
    }

    /**
     * Descuenta del saldo.
     *
     * @param  float  $piso  Saldo mínimo permitido después del débito. Por
     *                       defecto 0 (no se puede quedar en negativo); para
     *                       la comisión en efectivo se pasa el tope negativo
     *                       configurado.
     */
    public function debitar(
        Wallet $billetera,
        float $monto,
        WalletTransactionType $tipo,
        string $descripcion,
        ?Model $referencia = null,
        ?User $autor = null,
        array $metadata = [],
        float $piso = 0.0,
    ): WalletTransaction {
        $monto = round(abs($monto), 2);

        if ($monto <= 0) {
            throw new \InvalidArgumentException('El monto a debitar debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($billetera, $monto, $tipo, $descripcion, $referencia, $autor, $metadata, $piso): WalletTransaction {
            $billetera = $this->bloquear($billetera);
            $nuevo = round((float) $billetera->balance - $monto, 2);

            if ($nuevo < round($piso, 2)) {
                throw SaldoInsuficienteException::paraOperar((float) $billetera->balance, $monto);
            }

            $billetera->balance = $nuevo;
            $billetera->save();

            return $this->registrar($billetera, $tipo, 'debit', $monto, $descripcion, $referencia, $autor, $metadata);
        });
    }

    /**
     * Congela dinero para un retiro en proceso.
     *
     * No genera movimiento porque el dinero sigue siendo de la persona; solo
     * deja de estar disponible.
     */
    public function retener(Wallet $billetera, float $monto): Wallet
    {
        $monto = round(abs($monto), 2);

        return DB::transaction(function () use ($billetera, $monto): Wallet {
            $billetera = $this->bloquear($billetera);
            $disponible = $this->disponible($billetera);

            if ($monto <= 0) {
                throw new \InvalidArgumentException('El monto a retener debe ser mayor que cero.');
            }

            if ($disponible < $monto) {
                throw SaldoInsuficienteException::paraRetirar($disponible, $monto);
            }

            $billetera->held_balance = round((float) $billetera->held_balance + $monto, 2);
            $billetera->save();

            return $billetera;
        });
    }

    /** Libera una retención que no se llegó a pagar. */
    public function liberarRetencion(Wallet $billetera, float $monto): Wallet
    {
        $monto = round(abs($monto), 2);

        return DB::transaction(function () use ($billetera, $monto): Wallet {
            $billetera = $this->bloquear($billetera);
            $billetera->held_balance = max(0, round((float) $billetera->held_balance - $monto, 2));
            $billetera->save();

            return $billetera;
        });
    }

    /**
     * Ejecuta una retención: el dinero sale del saldo y deja de estar retenido,
     * con su movimiento en el libro mayor.
     */
    public function consumirRetencion(
        Wallet $billetera,
        float $monto,
        WalletTransactionType $tipo,
        string $descripcion,
        ?Model $referencia = null,
        ?User $autor = null,
        array $metadata = [],
    ): WalletTransaction {
        $monto = round(abs($monto), 2);

        return DB::transaction(function () use ($billetera, $monto, $tipo, $descripcion, $referencia, $autor, $metadata): WalletTransaction {
            $billetera = $this->bloquear($billetera);

            if (round((float) $billetera->held_balance, 2) < $monto) {
                throw new \RuntimeException('La retención de la billetera no coincide con el monto a pagar.');
            }

            $billetera->balance = round((float) $billetera->balance - $monto, 2);
            $billetera->held_balance = round((float) $billetera->held_balance - $monto, 2);
            $billetera->save();

            return $this->registrar($billetera, $tipo, 'debit', $monto, $descripcion, $referencia, $autor, $metadata);
        });
    }

    /**
     * Procesa de forma atómica la transferencia y cobro del viaje entre billeteras.
     *
     * Ejecuta dentro de una transacción de base de datos estricta (DB::transaction)
     * y bloquea ambas billeteras con lockForUpdate() ordenadas por ID para evitar
     * interbloqueos (deadlocks).
     *
     * Valida que el pasajero tenga saldo suficiente antes de cualquier descuento.
     * Si el descuento al pasajero o la acreditación al conductor fallan, la transacción
     * completa se revierte automáticamente (rollback) para evitar saldos desbalanceados
     * o pérdidas de dinero.
     */
    public function transferirPagoViaje(
        Wallet $billeteraPasajero,
        Wallet $billeteraConductor,
        float $montoPasajero,
        float $gananciaConductor,
        float $subsidioConductor,
        Trip $viaje,
    ): void {
        $montoPasajero = round(abs($montoPasajero), 2);
        $gananciaConductor = round(abs($gananciaConductor), 2);
        $subsidioConductor = round(abs($subsidioConductor), 2);

        DB::transaction(function () use (
            $billeteraPasajero,
            $billeteraConductor,
            $montoPasajero,
            $gananciaConductor,
            $subsidioConductor,
            $viaje,
        ): void {
            // Ordenar IDs para garantizar bloqueo en el mismo orden y prevenir deadlocks
            $ids = array_values(array_unique([$billeteraPasajero->getKey(), $billeteraConductor->getKey()]));
            sort($ids);

            $billeterasBloqueadas = Wallet::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Wallet $pasajeroLocked */
            $pasajeroLocked = $billeterasBloqueadas->get($billeteraPasajero->getKey())
                ?? $this->bloquear($billeteraPasajero);

            /** @var Wallet $conductorLocked */
            $conductorLocked = $billeterasBloqueadas->get($billeteraConductor->getKey())
                ?? $this->bloquear($billeteraConductor);

            // Validar que el pasajero tenga saldo suficiente antes de descontar
            if ($montoPasajero > 0) {
                // Si había saldo retenido para este viaje, se considera parte de lo utilizable para pagarlo
                $retencionViaje = min((float) $viaje->wallet_hold_amount, (float) $pasajeroLocked->held_balance);
                $otraRetencion = max(0.0, round((float) $pasajeroLocked->held_balance - $retencionViaje, 2));
                $disponibleParaViaje = round((float) $pasajeroLocked->balance - $otraRetencion, 2);

                if ($disponibleParaViaje < $montoPasajero) {
                    throw SaldoInsuficienteException::paraOperar(
                        $disponibleParaViaje,
                        $montoPasajero,
                    );
                }

                // Descontar saldo al pasajero y consumir la retención
                $pasajeroLocked->balance = round((float) $pasajeroLocked->balance - $montoPasajero, 2);
                if ($retencionViaje > 0) {
                    $pasajeroLocked->held_balance = max(0.0, round((float) $pasajeroLocked->held_balance - $retencionViaje, 2));
                }
                $pasajeroLocked->save();

                $this->registrar(
                    $pasajeroLocked,
                    WalletTransactionType::TripCharge,
                    'debit',
                    $montoPasajero,
                    "Pago de viaje · {$viaje->origin_address} → {$viaje->destination_address}",
                    $viaje,
                    null,
                    ['trip_id' => $viaje->getKey()],
                );
            }

            // Acreditar ganancia al conductor
            if ($gananciaConductor > 0) {
                $conductorLocked->balance = round((float) $conductorLocked->balance + $gananciaConductor, 2);
                $conductorLocked->save();

                $this->registrar(
                    $conductorLocked,
                    WalletTransactionType::TripEarning,
                    'credit',
                    $gananciaConductor,
                    "Ganancia de viaje · {$viaje->origin_address} → {$viaje->destination_address}",
                    $viaje,
                    null,
                    ['trip_id' => $viaje->getKey()],
                );
            }

            // Acreditar subsidio al conductor (si aplica por descuento/promoción)
            if ($subsidioConductor > 0) {
                $conductorLocked->balance = round((float) $conductorLocked->balance + $subsidioConductor, 2);
                $conductorLocked->save();

                $this->registrar(
                    $conductorLocked,
                    WalletTransactionType::Subsidy,
                    'credit',
                    $subsidioConductor,
                    "Subsidio por descuento aplicado · {$viaje->origin_address} → {$viaje->destination_address}",
                    $viaje,
                    null,
                    ['trip_id' => $viaje->getKey()],
                );
            }
        });
    }

    private function bloquear(Wallet $billetera): Wallet
    {
        return Wallet::query()
            ->whereKey($billetera->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function registrar(
        Wallet $billetera,
        WalletTransactionType $tipo,
        string $direccion,
        float $monto,
        string $descripcion,
        ?Model $referencia,
        ?User $autor,
        array $metadata,
    ): WalletTransaction {
        return $billetera->transactions()->create([
            'type' => $tipo,
            'direction' => $direccion,
            'amount' => $monto,
            'balance_after' => $billetera->balance,
            'held_after' => $billetera->held_balance,
            'description' => $descripcion,
            'reference_type' => $referencia?->getMorphClass(),
            'reference_id' => $referencia?->getKey(),
            'created_by' => $autor?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
