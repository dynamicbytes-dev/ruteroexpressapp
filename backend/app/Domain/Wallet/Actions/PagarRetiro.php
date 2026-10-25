<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\WalletService;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * El auditor marca un retiro como pagado y adjunta el comprobante de la
 * transferencia. Ahí recién sale el dinero de la billetera del conductor.
 */
class PagarRetiro
{
    public function __construct(private readonly WalletService $billetera) {}

    public function ejecutar(Withdrawal $retiro, User $auditor, UploadedFile $comprobante): Withdrawal
    {
        return DB::transaction(function () use ($retiro, $auditor, $comprobante): Withdrawal {
            /** @var Withdrawal $retiro */
            $retiro = Withdrawal::query()->whereKey($retiro->getKey())->lockForUpdate()->firstOrFail();

            if ($retiro->status !== WithdrawalStatus::Pending) {
                throw new RuntimeException('Este retiro ya fue procesado.');
            }

            $disco = (string) config('motoruta.documents.disk', 'local');
            $carpeta = 'comprobantes/retiros/'.$retiro->getKey();
            $nombre = sprintf(
                'retiro-%s.%s',
                Str::lower(Str::ulid()->toBase32()),
                $comprobante->getClientOriginalExtension() ?: $comprobante->extension() ?: 'jpg',
            );

            $ruta = $comprobante->storeAs($carpeta, $nombre, ['disk' => $disco]);

            if ($ruta === false) {
                throw new RuntimeException('No pudimos guardar el comprobante del retiro.');
            }

            $this->billetera->consumirRetencion(
                $this->billetera->paraUsuario($retiro->user),
                (float) $retiro->amount_usd,
                WalletTransactionType::Withdrawal,
                "Retiro pagado · {$retiro->bank} · {$retiro->account_number}",
                $retiro,
                $auditor,
                ['cuenta' => $retiro->account_number, 'titular' => $retiro->account_holder],
            );

            $retiro->update([
                'status' => WithdrawalStatus::Paid,
                'proof_path' => $ruta,
                'proof_disk' => $disco,
                'proof_original_name' => $comprobante->getClientOriginalName(),
                'proof_mime_type' => $comprobante->getMimeType(),
                'proof_size_bytes' => $comprobante->getSize(),
                'reviewed_by' => $auditor->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $retiro->refresh();
        });
    }
}
