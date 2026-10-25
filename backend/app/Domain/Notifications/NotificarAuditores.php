<?php

namespace App\Domain\Notifications;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AvisoAuditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envía un aviso a todos los auditores aprobados.
 *
 * Se usa `afterCommit` para que ninguna notificación se pierda si la operación
 * que la origina termina en rollback. Si no hay transacción abierta se ejecuta
 * de inmediato.
 */
class NotificarAuditores
{
    /** @param array<string, mixed> $datos */
    public function ejecutar(
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $url = null,
        array $datos = [],
        ?int $exceptoUserId = null,
    ): void {
        DB::afterCommit(function () use ($tipo, $titulo, $mensaje, $url, $datos, $exceptoUserId): void {
            $auditores = User::query()
                ->where('role', UserRole::Auditor)
                ->where('status', UserStatus::Approved)
                ->when($exceptoUserId !== null, fn ($query) => $query->whereKeyNot($exceptoUserId))
                ->get();

            if ($auditores->isEmpty()) {
                return;
            }

            try {
                Notification::send($auditores, new AvisoAuditor($tipo, $titulo, $mensaje, $url, $datos));
            } catch (Throwable $error) {
                // Un aviso fallido no puede tumbar la operación que lo originó.
                Log::error('No se pudo enviar el aviso a los auditores.', [
                    'tipo' => $tipo,
                    'error' => $error->getMessage(),
                ]);
            }
        });
    }
}
