<?php

namespace App\Http\Controllers\Panel;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cola de verificación de usuarios.
 *
 * El auditor trabaja por pestañas, no por búsqueda: primero lo que espera
 * decisión (en cola), después lo que la persona debe corregir (rechazados), y
 * luego el resto para consultar.
 */
class UserController extends Controller
{
    /** Pestañas disponibles, con el filtro que aplica cada una. */
    public const TABS = [
        'cola' => 'Por verificar',
        'rechazados' => 'Deben corregir',
        'documentos' => 'Documentos por revisar',
        'aprobados' => 'Aprobados',
        'sin_documentos' => 'Registro incompleto',
        'todos' => 'Todos',
    ];

    public function index(Request $request): View
    {
        $tab = $this->tabValida($request->query('tab'));
        $rol = $this->rolValido($request->query('rol'));
        $busqueda = trim((string) $request->query('buscar', ''));

        $usuarios = User::query()
            ->selfRegistrable()
            ->when($rol !== null, fn (Builder $query) => $query->role($rol))
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $termino = '%'.mb_strtolower($busqueda).'%';

                $query->where(function (Builder $query) use ($termino): void {
                    $query->whereRaw('lower(first_name) like ?', [$termino])
                        ->orWhereRaw('lower(last_name) like ?', [$termino])
                        ->orWhereRaw('lower(email) like ?', [$termino])
                        ->orWhere('national_id', 'like', '%'.preg_replace('/\D/', '', $busqueda).'%')
                        ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $busqueda).'%');
                });
            })
            ->withCount([
                'documents',
                'documents as rejected_documents_count' => fn ($query) => $query->where('status', DocumentStatus::Rejected),
                'documents as pending_documents_count' => fn ($query) => $query->where('status', DocumentStatus::Pending),
            ])
            ->orderByRaw('submitted_at asc nulls last')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('panel.usuarios.index', [
            'usuarios' => $usuarios,
            'tabs' => self::TABS,
            'tab' => $tab,
            'rol' => $rol,
            'busqueda' => $busqueda,
            'contadores' => $this->contadores(),
        ]);
    }

    public function show(User $user): View
    {
        // El panel de verificación es para pasajeros y conductores.
        abort_if($user->isAuditor(), 404);

        $user->load([
            'driverProfile',
            'driverProfile.city',
            'city',
            'wallet',
            'documents.reviewer',
            'verificationReviews.auditor',
        ]);

        return view('panel.usuarios.show', [
            'usuario' => $user,
            'documentos' => $this->documentosOrdenados($user),
            'revisiones' => $user->verificationReviews()->latest()->get(),
        ]);
    }

    /**
     * Actualiza los datos clave de un usuario.
     * Solo permitido para el Auditor Principal (is_owner / is_main_auditor).
     */
    public function update(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $auditor = $request->user();

        // 1. RESTRICCIÓN DE ROL: Solo el Auditor Principal (is_owner === true) puede editar
        if (! $auditor || ! ($auditor->isOwner() || $auditor->is_main_auditor)) {
            abort(403, 'Acción no autorizada. Solo el Auditor Principal tiene permisos para editar estos datos.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => [
                'required',
                'string',
                'max:25',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $user->update($validated);

        activity()
            ->performedOn($user)
            ->causedBy($auditor)
            ->log("El Auditor Principal actualizó los datos del usuario ID #{$user->id}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado correctamente.',
                'user' => $user->fresh(),
            ]);
        }

        return back()->with('status', 'Usuario actualizado correctamente.');
    }

    /** Documentos en el orden del rol, para que la ficha siempre se vea igual. */
    private function documentosOrdenados(User $user): array
    {
        $porTipo = $user->documents->keyBy(fn ($documento): string => $documento->type->value);

        return array_map(
            fn ($tipo): ?\App\Models\UserDocument => $porTipo->get($tipo->value),
            $user->role->requiredDocuments(),
        );
    }

    /** Total por pestaña, para que el auditor vea dónde está el trabajo. */
    private function contadores(): array
    {
        return [
            'cola' => User::query()->selfRegistrable()->pendingReview()->count(),
            'rechazados' => User::query()->selfRegistrable()->needsCorrection()->count(),
            'documentos' => User::query()
                ->selfRegistrable()
                ->where('status', UserStatus::Approved)
                ->whereHas('documents', fn (Builder $query) => $query->where('status', DocumentStatus::Pending))
                ->count(),
            'aprobados' => User::query()->selfRegistrable()->where('status', UserStatus::Approved)->count(),
            'sin_documentos' => User::query()
                ->selfRegistrable()
                ->where('status', UserStatus::Pending)
                ->whereNull('submitted_at')
                ->count(),
        ];
    }

    /** @param  Builder<User>  $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'cola' => $query->pendingReview(),
            'rechazados' => $query->needsCorrection(),
            'documentos' => $query
                ->where('status', UserStatus::Approved)
                ->whereHas('documents', fn (Builder $sub) => $sub->where('status', DocumentStatus::Pending)),
            'aprobados' => $query->where('status', UserStatus::Approved),
            'sin_documentos' => $query->where('status', UserStatus::Pending)->whereNull('submitted_at'),
            default => null,
        };
    }

    private function tabValida(mixed $tab): string
    {
        return is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'cola';
    }

    private function rolValido(mixed $rol): ?UserRole
    {
        if (! is_string($rol)) {
            return null;
        }

        return UserRole::tryFrom($rol);
    }
}
