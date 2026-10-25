<?php

namespace App\Http\Controllers\Panel;

use App\Enums\DocumentStatus;
use App\Enums\RechargeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Recharge;
use App\Models\SupportConversation;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDocument;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Cada cola de trabajo del auditor, con su total, para que la primera
        // pantalla diga qué hay pendiente hoy.
        $queues = [
            [
                'key' => 'usuarios',
                'label' => 'Usuarios por verificar',
                'help' => 'Pasajeros y conductores esperando revisión de documentos',
                'count' => User::query()->pendingReview()->whereIn('role', UserRole::selfRegistrable())->count(),
                'url' => route('panel.usuarios.index'),
            ],
            [
                'key' => 'documentos',
                'label' => 'Documentos por revisar',
                'help' => 'Archivos nuevos o renovados de cuentas ya aprobadas',
                'count' => User::query()
                    ->whereIn('role', UserRole::selfRegistrable())
                    ->where('status', UserStatus::Approved)
                    ->whereHas('documents', fn ($query) => $query->where('status', DocumentStatus::Pending))
                    ->count(),
                'url' => route('panel.usuarios.index', ['tab' => 'documentos']),
            ],
            [
                'key' => 'recargas',
                'label' => 'Recargas por aprobar',
                'help' => 'Comprobantes de transferencia bancaria',
                'count' => Recharge::query()->where('status', RechargeStatus::Pending)->count(),
                'url' => route('panel.recargas.index'),
            ],
            [
                'key' => 'retiros',
                'label' => 'Retiros solicitados',
                'help' => 'Pagos por transferir a conductores',
                'count' => Withdrawal::query()->where('status', WithdrawalStatus::Pending)->count(),
                'url' => route('panel.retiros.index'),
            ],
            [
                'key' => 'soporte',
                'label' => 'Conversaciones de soporte',
                'help' => 'Chats abiertos con pasajeros y conductores',
                'count' => SupportConversation::query()->open()->count(),
                'url' => route('panel.soporte.index'),
            ],
            [
                'key' => 'viajes',
                'label' => 'Viajes en curso',
                'help' => 'Viajes activos y su estado de asignación',
                'count' => Trip::query()->active()->count(),
                'url' => route('panel.viajes.index'),
            ],
        ];

        $summary = [
            'conductores_activos' => User::query()
                ->role(UserRole::Driver)
                ->where('status', UserStatus::Approved)
                ->count(),
            'pasajeros' => User::query()->role(UserRole::Passenger)->count(),
            'aprobados' => User::query()
                ->selfRegistrable()
                ->where('status', UserStatus::Approved)
                ->count(),
            'rechazados' => UserDocument::query()
                ->where('status', DocumentStatus::Rejected)
                ->count(),
        ];

        $documentos = $this->documentosPorVencer();

        return view('panel.dashboard', [
            'queues' => $queues,
            'summary' => $summary,
            'documentosPorVencer' => $documentos['por_vencer'],
            'documentosVencidos' => $documentos['vencidos'],
            'recent' => User::query()
                ->with('documents')
                ->whereIn('role', UserRole::selfRegistrable())
                ->latest('submitted_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Documentos de conductores que necesitan atención: los que vencen en los
     * próximos 30 días y los que ya vencieron.
     *
     * @return array{por_vencer: Collection<int, UserDocument>, vencidos: Collection<int, UserDocument>}
     */
    private function documentosPorVencer(): array
    {
        $base = UserDocument::query()
            ->whereNotNull('expires_at')
            ->whereHas('user', function ($query): void {
                $query->where('role', UserRole::Driver)
                    ->whereIn('status', [UserStatus::Approved, UserStatus::Pending]);
            })
            ->with('user');

        return [
            'por_vencer' => (clone $base)
                ->whereDate('expires_at', '>=', today()->toDateString())
                ->whereDate('expires_at', '<=', today()->addDays(30)->toDateString())
                ->orderBy('expires_at')
                ->limit(20)
                ->get(),
            'vencidos' => (clone $base)
                ->whereDate('expires_at', '<', today()->toDateString())
                ->orderBy('expires_at')
                ->limit(20)
                ->get(),
        ];
    }
}
