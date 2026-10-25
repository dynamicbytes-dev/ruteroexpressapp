<?php

namespace App\Console\Commands;

use App\Domain\Notifications\NotificarAuditores;
use App\Domain\Trips\TripDispatcher;
use App\Enums\DocumentType;
use App\Enums\UserStatus;
use App\Models\UserDocument;
use App\Notifications\DocumentoPorVencer;
use Illuminate\Console\Command;

/**
 * Avisa a los conductores cuando un documento del vehículo o la licencia está
 * por vencer (30, 15 y 5 días) y pasa la cuenta a revisión cuando ya venció.
 *
 * El aviso se guarda en la base de datos; la app lo consulta por API y en F4
 * el mismo registro disparará la notificación push.
 */
class AvisarDocumentosPorVencer extends Command
{
    protected $signature = 'documentos:avisar-vencimientos';

    protected $description = 'Avisa documentos por vencer y pasa a revisión las cuentas con documentos vencidos';

    public function __construct(
        private readonly TripDispatcher $asignador,
        private readonly NotificarAuditores $notificar,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $avisados = 0;
        $revisados = 0;
        $limite = now()->startOfDay()->addDays(30);

        UserDocument::query()
            ->whereNotNull('expires_at')
            ->whereIn('type', $this->tiposConVencimiento())
            ->whereDate('expires_at', '<=', $limite->toDateString())
            ->with('user')
            ->chunkById(100, function ($documentos) use (&$avisados, &$revisados): void {
                foreach ($documentos as $documento) {
                    $usuario = $documento->user;

                    if ($usuario === null) {
                        continue;
                    }

                    $dias = $documento->daysUntilExpiration();

                    if ($dias === null) {
                        continue;
                    }

                    $umbral = $this->umbralPendiente($documento, $dias);

                    if ($umbral === null) {
                        continue;
                    }

                    if ($dias < 0 && $this->pasarVehiculoARevision($documento)) {
                        $revisados++;
                    }

                    $usuario->notify(new DocumentoPorVencer($documento, $dias));

                    $documento->forceFill(['expiry_notice_days' => $umbral])->save();
                    $avisados++;
                }
            });

        $this->info("Avisos enviados: {$avisados}. Cuentas pasadas a revisión: {$revisados}.");

        if ($avisados > 0 || $revisados > 0) {
            $this->notificar->ejecutar(
                'documentos_vencimiento',
                'Documentos por vencer o vencidos',
                "Se avisaron {$avisados} documento(s) y se pasaron {$revisados} cuenta(s) a revisión por vencimiento.",
                route('panel.conductores.index'),
                ['avisados' => $avisados, 'revisados' => $revisados],
            );
        }

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function tiposConVencimiento(): array
    {
        return array_values(array_map(
            static fn (DocumentType $tipo): string => $tipo->value,
            array_filter(
                DocumentType::cases(),
                static fn (DocumentType $tipo): bool => $tipo->expires(),
            ),
        ));
    }

    /**
     * Devuelve el umbral que toca avisar, o null si ya se envió.
     *
     * Los umbrales se recorren de menor a mayor para que un documento que ya
     * venció no reciba primero el aviso de 30 días.
     */
    private function umbralPendiente(UserDocument $documento, int $dias): ?int
    {
        $ultimo = $documento->expiry_notice_days;

        foreach ([0, 5, 15, 30] as $umbral) {
            if ($dias <= $umbral && ($ultimo === null || $umbral < $ultimo)) {
                return $umbral;
            }
        }

        return null;
    }

    /**
     * Un documento vencido saca al conductor de línea y devuelve la cuenta a
     * la cola del auditor. El documento sigue aprobado pero vencido, y
     * `canBeApproved()` impide cerrar la revisión hasta que se reenvíe.
     */
    private function pasarVehiculoARevision(UserDocument $documento): bool
    {
        $usuario = $documento->user;

        if ($usuario === null || ! $usuario->isDriver() || $usuario->status !== UserStatus::Approved) {
            return false;
        }

        $usuario->forceFill([
            'status' => UserStatus::Pending,
            'status_reason' => "Documento vencido · {$documento->type->label()}: venció el "
                .$documento->expires_at?->format('d/m/Y')
                .'. Súbelo de nuevo para volver a revisión.',
            'submitted_at' => now(),
            'approved_at' => null,
        ])->save();

        $perfil = $usuario->driverProfile;

        if ($perfil !== null) {
            $perfil->forceFill([
                'is_online' => false,
                'went_online_at' => null,
            ])->save();
        }

        $this->asignador->cancelarOfertaPendienteDeConductor($usuario);

        return true;
    }
}
