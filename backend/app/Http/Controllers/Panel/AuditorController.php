<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RechargeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Recharge;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\VerificationReview;
use App\Models\Withdrawal;
use App\Rules\TelefonoVe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Registro de nuevos auditores desde el panel.
 *
 * Solo el auditor principal (clase A), dueño del sistema, puede crear otras
 * cuentas de auditor. Las cuentas creadas no heredan ese privilegio. La cuenta
 * nace aprobada y con el correo verificado: el nuevo auditor entra con la
 * contraseña que se define aquí y puede activar su doble factor desde su perfil.
 */
class AuditorController extends Controller
{
    public function index(Request $request): View
    {
        $auditores = User::query()
            ->role(UserRole::Auditor)
            ->orderByDesc('is_owner')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20);

        return view('panel.auditores.index', [
            'auditores' => $auditores,
            'puedeCrear' => $request->user()->isOwner(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->soloAuditorPrincipal($request);

        return view('panel.auditores.create');
    }

    /**
     * Rendimiento de los auditores secundarios.
     *
     * El auditor principal ve, en el período elegido, cuántas decisiones tomó
     * cada persona, cuánto atendió el soporte y cuántas operaciones financieras
     * revisó. Es una vista de solo lectura: sirve para repartir el trabajo y
     * detectar cargas desbalanceadas.
     */
    public function rendimiento(Request $request): View
    {
        $this->soloAuditorPrincipal($request);

        $dias = (int) $request->integer('dias', 30);

        if (! in_array($dias, [7, 30, 90], true)) {
            $dias = 30;
        }

        $desde = now()->subDays($dias - 1)->startOfDay();
        $hasta = now()->endOfDay();

        $auditores = User::query()
            ->role(UserRole::Auditor)
            ->where('is_owner', false)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $ids = $auditores->pluck('id')->all();

        $revisiones = VerificationReview::query()
            ->whereIn('auditor_id', $ids)
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('auditor_id, count(*) as total')
            ->selectRaw(
                'sum(case when decision = ? then 1 else 0 end) as aprobaciones',
                [VerificationReview::DECISION_APPROVED],
            )
            ->selectRaw(
                'sum(case when decision = ? then 1 else 0 end) as rechazos',
                [VerificationReview::DECISION_REJECTED],
            )
            ->selectRaw(
                'sum(case when decision = ? then 1 else 0 end) as suspensiones',
                [VerificationReview::DECISION_SUSPENDED],
            )
            ->selectRaw(
                'sum(case when decision = ? then 1 else 0 end) as reactivaciones',
                [VerificationReview::DECISION_REACTIVATED],
            )
            ->groupBy('auditor_id')
            ->get()
            ->keyBy('auditor_id');

        $mensajesSoporte = SupportMessage::query()
            ->whereIn('sender_id', $ids)
            ->where('sender_type', SupportMessage::AUTOR_AUDITOR)
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('sender_id, count(*) as total')
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        $cierresSoporte = SupportConversation::query()
            ->whereIn('closed_by', $ids)
            ->whereBetween('closed_at', [$desde, $hasta])
            ->selectRaw('closed_by, count(*) as total')
            ->groupBy('closed_by')
            ->pluck('total', 'closed_by');

        $recargas = Recharge::query()
            ->whereIn('reviewed_by', $ids)
            ->whereBetween('reviewed_at', [$desde, $hasta])
            ->selectRaw('reviewed_by, count(*) as total')
            ->selectRaw(
                'sum(case when status = ? then 1 else 0 end) as aprobadas',
                [RechargeStatus::Approved->value],
            )
            ->selectRaw(
                'sum(case when status = ? then 1 else 0 end) as rechazadas',
                [RechargeStatus::Rejected->value],
            )
            ->groupBy('reviewed_by')
            ->get()
            ->keyBy('reviewed_by');

        $retiros = Withdrawal::query()
            ->whereIn('reviewed_by', $ids)
            ->whereBetween('reviewed_at', [$desde, $hasta])
            ->selectRaw('reviewed_by, count(*) as total')
            ->selectRaw(
                'sum(case when status = ? then 1 else 0 end) as pagados',
                [WithdrawalStatus::Paid->value],
            )
            ->selectRaw(
                'sum(case when status = ? then 1 else 0 end) as rechazados',
                [WithdrawalStatus::Rejected->value],
            )
            ->groupBy('reviewed_by')
            ->get()
            ->keyBy('reviewed_by');

        $ultimaActividad = Activity::query()
            ->where('causer_type', User::class)
            ->whereIn('causer_id', $ids)
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw('causer_id, max(created_at) as ultima')
            ->groupBy('causer_id')
            ->pluck('ultima', 'causer_id');

        $filas = $auditores->map(function (User $auditor) use (
            $dias,
            $revisiones,
            $mensajesSoporte,
            $cierresSoporte,
            $recargas,
            $retiros,
            $ultimaActividad,
        ): array {
            $revision = $revisiones->get($auditor->id);
            $recarga = $recargas->get($auditor->id);
            $retiro = $retiros->get($auditor->id);

            $decisiones = (int) ($revision->total ?? 0);
            $aprobaciones = (int) ($revision->aprobaciones ?? 0);
            $rechazos = (int) ($revision->rechazos ?? 0);
            $soporte = (int) ($mensajesSoporte[$auditor->id] ?? 0);
            $cierres = (int) ($cierresSoporte[$auditor->id] ?? 0);
            $recargasTotal = (int) ($recarga->total ?? 0);
            $retirosTotal = (int) ($retiro->total ?? 0);

            $gestiones = $decisiones + $soporte + $cierres + $recargasTotal + $retirosTotal;
            $evaluadas = $aprobaciones + $rechazos;

            return [
                'auditor' => $auditor,
                'decisiones' => $decisiones,
                'aprobaciones' => $aprobaciones,
                'rechazos' => $rechazos,
                'suspensiones' => (int) ($revision->suspensiones ?? 0),
                'reactivaciones' => (int) ($revision->reactivaciones ?? 0),
                'tasa_aprobacion' => $evaluadas > 0
                    ? (int) round($aprobaciones / $evaluadas * 100)
                    : null,
                'soporte_mensajes' => $soporte,
                'soporte_cierres' => $cierres,
                'recargas' => $recargasTotal,
                'recargas_aprobadas' => (int) ($recarga->aprobadas ?? 0),
                'retiros' => $retirosTotal,
                'retiros_pagados' => (int) ($retiro->pagados ?? 0),
                'gestiones' => $gestiones,
                'promedio_diario' => round($gestiones / $dias, 1),
                'ultima_actividad' => isset($ultimaActividad[$auditor->id])
                    ? \Illuminate\Support\Carbon::parse($ultimaActividad[$auditor->id])
                    : null,
            ];
        })->sortByDesc('gestiones')->values();

        return view('panel.auditores.rendimiento', [
            'filas' => $filas,
            'dias' => $dias,
            'desde' => $desde,
            'hasta' => $hasta,
            'resumen' => [
                'auditores' => $filas->count(),
                'decisiones' => $filas->sum('decisiones'),
                'soporte' => $filas->sum('soporte_mensajes') + $filas->sum('soporte_cierres'),
                'finanzas' => $filas->sum('recargas') + $filas->sum('retiros'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->soloAuditorPrincipal($request);

        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $datos = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'national_id' => ['required', 'digits_between:6,9', Rule::unique('users', 'national_id')],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', new TelefonoVe],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ], messages: [
            'national_id.unique' => 'Esa cédula ya está registrada.',
            'email.unique' => 'Ese correo ya tiene una cuenta.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ], attributes: [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'national_id' => 'cédula',
            'email' => 'correo',
            'phone' => 'teléfono',
            'password' => 'contraseña',
        ]);

        $auditor = DB::transaction(function () use ($datos, $request): User {
            $auditor = User::create([
                'role' => UserRole::Auditor,
                'is_owner' => false,
                'first_name' => $datos['first_name'],
                'last_name' => $datos['last_name'],
                'national_id' => $datos['national_id'],
                'email' => $datos['email'],
                'phone' => $datos['phone'],
                'address' => 'Oficina GAZ',
                'password' => $datos['password'],
                'status' => UserStatus::Approved,
            ]);

            // `approved_at` y `email_verified_at` no son asignables en masa:
            // se marcan explícitamente porque esta cuenta nace habilitada.
            $auditor->forceFill([
                'approved_at' => now(),
                'email_verified_at' => now(),
            ])->save();

            $rol = Role::findOrCreate('auditor', 'web');
            $auditor->assignRole($rol);

            activity('auditores')
                ->causedBy($request->user())
                ->performedOn($auditor)
                ->withProperties(['email' => $auditor->email])
                ->log('Auditor creado desde el panel');

            return $auditor;
        });

        return redirect()
            ->route('panel.auditores.index')
            ->with('status', "Creaste la cuenta de auditor de {$auditor->name}. Ya puede entrar con {$auditor->email}.");
    }

    public function update(Request $request, User $auditor): RedirectResponse|JsonResponse
    {
        $this->soloAuditorPrincipal($request);

        abort_unless($auditor->isAuditor(), 404);

        $datos = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', Rule::unique('users', 'phone')->ignore($auditor->id)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'is_owner' => ['sometimes', 'boolean'],
        ], attributes: [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'phone' => 'teléfono',
            'status' => 'estatus',
            'is_owner' => 'rango de auditor principal',
        ]);

        $nuevoIsOwner = $request->has('is_owner') ? $request->boolean('is_owner') : $auditor->is_owner;

        // Protección: Si es el auditor principal autenticado, no puede degradarse a sí mismo si es el único dueño.
        if ($auditor->id === $request->user()->id && ! $nuevoIsOwner) {
            $otrosPrincipales = User::query()
                ->role(UserRole::Auditor)
                ->where('is_owner', true)
                ->where('id', '!=', $auditor->id)
                ->exists();

            if (! $otrosPrincipales) {
                return back()->withErrors(['is_owner' => 'No puedes removerte el rango de Auditor Principal siendo el único principal en el sistema.']);
            }
        }

        // Tampoco puede suspenderse a sí mismo
        if ($auditor->id === $request->user()->id && $datos['status'] !== UserStatus::Approved->value) {
            return back()->withErrors(['status' => 'No puedes suspender tu propia cuenta activa de Auditor Principal.']);
        }

        $cambios = [];
        if ($auditor->first_name !== $datos['first_name']) {
            $cambios['first_name'] = [$auditor->first_name, $datos['first_name']];
        }
        if ($auditor->last_name !== $datos['last_name']) {
            $cambios['last_name'] = [$auditor->last_name, $datos['last_name']];
        }
        if ($auditor->phone !== $datos['phone']) {
            $cambios['phone'] = [$auditor->phone, $datos['phone']];
        }
        if ($auditor->status->value !== $datos['status']) {
            $cambios['status'] = [$auditor->status->value, $datos['status']];
        }
        if ((bool) $auditor->is_owner !== (bool) $nuevoIsOwner) {
            $cambios['is_owner'] = [(bool) $auditor->is_owner, (bool) $nuevoIsOwner];
        }

        DB::transaction(function () use ($auditor, $datos, $nuevoIsOwner, $request, $cambios): void {
            if ($nuevoIsOwner && ! $auditor->is_owner) {
                // Solo puede existir un Auditor Principal a la vez (índice único users_is_owner_unique).
                // Al promover a este auditor, se transfiere la titularidad desde el auditor principal actual.
                User::query()
                    ->where('is_owner', true)
                    ->where('id', '!=', $auditor->id)
                    ->update(['is_owner' => false]);
            }

            $auditor->update([
                'first_name' => $datos['first_name'],
                'last_name' => $datos['last_name'],
                'phone' => $datos['phone'],
                'status' => $datos['status'],
                'is_owner' => $nuevoIsOwner,
            ]);

            activity('auditores')
                ->causedBy($request->user())
                ->performedOn($auditor)
                ->withProperties(['cambios' => $cambios])
                ->log("Auditor {$auditor->name} actualizado por el Auditor Principal");
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Auditor actualizado exitosamente.',
                'auditor' => $auditor->fresh(),
            ]);
        }

        return redirect()
            ->route('panel.auditores.index')
            ->with('status', "Datos del auditor {$auditor->name} actualizados correctamente.");
    }

    /** El privilegio de gestionar auditores corresponde exclusivamente al auditor principal. */
    private function soloAuditorPrincipal(Request $request): void
    {
        abort_unless(
            $request->user()?->isOwner() === true || $request->user()?->is_main_auditor === true,
            403,
            'Solo el auditor principal puede gestionar cuentas de auditor.',
        );
    }
}
