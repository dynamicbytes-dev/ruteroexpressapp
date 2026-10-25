<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Notifications\NotificarAuditores;
use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Enums\RechargeStatus;
use App\Models\Recharge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registra una recarga con su comprobante.
 *
 * La tasa de cambio no se congela aquí: se aplica cuando el auditor aprueba,
 * porque la transferencia puede tardar en revisarse.
 */
class SolicitarRecarga
{
    public function __construct(private readonly NotificarAuditores $notificar) {}

    /**
     * @param  array{amount_bs: float|string, bank: string, reference: string, transferred_at: string}  $datos
     */
    public function ejecutar(User $usuario, array $datos, UploadedFile $comprobante): Recharge
    {
        if (! $usuario->canOperate()) {
            throw new OperacionNoPermitidaException(
                'Tu cuenta todavía no puede recargar: primero debe ser aprobada por el equipo.',
            );
        }

        if (! $usuario->isPassenger() && ! $usuario->isDriver()) {
            throw new OperacionNoPermitidaException('Solo pasajeros y conductores usan la billetera.');
        }

        $disco = (string) config('motoruta.documents.disk', 'local');
        $carpeta = 'comprobantes/recargas/'.$usuario->getKey();
        $nombre = sprintf(
            'recarga-%s.%s',
            Str::lower(Str::ulid()->toBase32()),
            $comprobante->getClientOriginalExtension() ?: $comprobante->extension() ?: 'jpg',
        );

        $ruta = $comprobante->storeAs($carpeta, $nombre, ['disk' => $disco]);

        if ($ruta === false) {
            throw ValidationException::withMessages([
                'comprobante' => 'No pudimos guardar el comprobante. Intenta de nuevo.',
            ]);
        }

        $recarga = Recharge::create([
            'user_id' => $usuario->getKey(),
            'amount_bs' => round((float) $datos['amount_bs'], 2),
            'bank' => $datos['bank'],
            'reference' => $datos['reference'],
            'transferred_at' => $datos['transferred_at'],
            'proof_path' => $ruta,
            'proof_disk' => $disco,
            'proof_original_name' => $comprobante->getClientOriginalName(),
            'proof_mime_type' => $comprobante->getMimeType(),
            'proof_size_bytes' => $comprobante->getSize(),
            'status' => RechargeStatus::Pending,
        ]);

        $this->notificar->ejecutar(
            'recarga_pendiente',
            'Nueva recarga por revisar',
            "{$usuario->name} reportó una recarga de Bs ".number_format((float) $recarga->amount_bs, 2, ',', '.')." ({$recarga->bank}).",
            route('panel.recargas.show', $recarga),
            ['recharge_id' => $recarga->id],
        );

        return $recarga;
    }
}
