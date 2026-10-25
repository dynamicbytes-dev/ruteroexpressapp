<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Verification\Actions\ReactivarCuenta;
use App\Domain\Verification\Actions\SuspenderCuenta;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Trip;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de gestión de Pasajeros.
 *
 * Módulo especializado para visualización, edición, control de estado (inhabilitar/habilitar),
 * historial de viajes, billetera y exportación de ficha técnica en PDF.
 */
class PassengerController extends Controller
{
    public const TABS = [
        'todos' => 'Todos',
        'activos' => 'Activos / Aprobados',
        'en_espera' => 'En espera de revisión',
        'inhabilitados' => 'Inhabilitados',
        'con_viajes' => 'Con viajes',
        'con_saldo' => 'Con saldo en billetera',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS)
            ? (string) $request->query('tab')
            : 'todos';

        $busqueda = trim((string) $request->query('buscar', ''));

        $pasajeros = User::query()
            ->role(UserRole::Passenger)
            ->with(['city', 'wallet'])
            ->withCount([
                'tripsAsPassenger as completed_trips_count' => fn (Builder $q) => $q->where('status', TripStatus::Completed),
                'tripsAsPassenger as cancelled_trips_count' => fn (Builder $q) => $q->where('status', TripStatus::Cancelled),
                'favoriteDrivers as favorites_count',
            ])
            ->withSum([
                'tripsAsPassenger as spent_sum' => fn (Builder $q) => $q->where('status', TripStatus::Completed),
            ], 'total')
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $digitos = preg_replace('/\D/', '', $busqueda) ?? '';

                $query->where(function (Builder $sub) use ($busqueda, $digitos): void {
                    $sub->where('first_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('last_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('email', 'ilike', "%{$busqueda}%");

                    if (strlen($digitos) >= 4) {
                        $sub->orWhere('national_id', 'like', "%{$digitos}%")
                            ->orWhere('phone', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('panel.pasajeros.index', [
            'pasajeros' => $pasajeros,
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'contadores' => $this->contadores(),
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->isPassenger(), 404);

        $user->load([
            'city',
            'wallet',
            'documents.reviewer',
            'verificationReviews.auditor',
            'favoriteDrivers.driverProfile.city',
        ]);

        $viajes = $user->tripsAsPassenger()
            ->with('driver')
            ->latest('requested_at')
            ->limit(15)
            ->get();

        $recargas = $user->recharges()
            ->latest('created_at')
            ->limit(10)
            ->get();

        $resumen = [
            'completados' => $user->tripsAsPassenger()->where('status', TripStatus::Completed)->count(),
            'gasto_total' => (float) $user->tripsAsPassenger()->where('status', TripStatus::Completed)->sum('total'),
            'cancelados' => $user->tripsAsPassenger()->where('status', TripStatus::Cancelled)->count(),
            'favoritos' => $user->favoriteDrivers()->count(),
            'recargas' => $user->recharges()->where('status', 'approved')->count(),
        ];

        return view('panel.pasajeros.show', [
            'pasajero' => $user,
            'viajes' => $viajes,
            'recargas' => $recargas,
            'resumen' => $resumen,
            'ciudades' => City::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->isPassenger(), 404);

        $user->load(['city', 'wallet']);

        return view('panel.pasajeros.edit', [
            'pasajero' => $user,
            'ciudades' => City::query()->active()->orderBy('name')->get(),
            'estados' => UserStatus::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isPassenger(), 404);

        $datos = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'national_id' => [
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'national_id')->ignore($user->getKey()),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('users', 'phone')->ignore($user->getKey()),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ], [
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'national_id.required' => 'La cédula es obligatoria.',
            'national_id.unique' => 'Esa cédula ya pertenece a otra cuenta.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Ese correo ya está registrado en otra cuenta.',
            'phone.unique' => 'Ese teléfono ya está registrado en otra cuenta.',
        ]);

        $user->fill($datos);
        $cambios = $user->getDirty();
        $user->save();

        activity('pasajeros')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['cambios' => $cambios])
            ->log("Datos del pasajero {$user->name} actualizados desde el panel");

        return redirect()
            ->route('panel.pasajeros.show', $user)
            ->with('status', "Datos de {$user->name} actualizados correctamente.");
    }

    /** Inhabilita al pasajero (suspensión de cuenta con motivo). */
    public function inhabilitar(
        Request $request,
        User $user,
        SuspenderCuenta $accion,
    ): RedirectResponse {
        abort_unless($user->isPassenger(), 404);

        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ], [
            'reason.required' => 'Indica el motivo de inhabilitación: el pasajero lo leerá.',
            'reason.min' => 'Explica el motivo con al menos 8 caracteres.',
            'reason.max' => 'El motivo no puede exceder los 500 caracteres.',
        ]);

        $accion->ejecutar($user, $request->user(), $datos['reason']);

        activity('pasajeros')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['motivo' => $datos['reason']])
            ->log("Pasajero {$user->name} inhabilitado");

        return back()->with('status', "Inhabilitaste a {$user->name}. Ya no puede solicitar viajes ni recargar.");
    }

    /** Reactiva al pasajero. */
    public function habilitar(
        Request $request,
        User $user,
        ReactivarCuenta $accion,
    ): RedirectResponse {
        abort_unless($user->isPassenger(), 404);

        $accion->ejecutar($user, $request->user());

        activity('pasajeros')
            ->performedOn($user)
            ->causedBy($request->user())
            ->log("Pasajero {$user->name} habilitado nuevamente");

        return back()->with('status', "Habilitaste la cuenta de {$user->name}. Ya puede solicitar viajes.");
    }

    /** Exporta la ficha técnica oficial del pasajero en PDF. */
    public function exportPdf(User $user): Response
    {
        abort_unless($user->isPassenger(), 404);

        $user->load([
            'city',
            'wallet',
            'documents',
            'favoriteDrivers',
        ]);

        $viajes = $user->tripsAsPassenger()
            ->with('driver')
            ->latest('created_at')
            ->limit(20)
            ->get();

        $resumen = [
            'completados' => $user->tripsAsPassenger()->where('status', TripStatus::Completed)->count(),
            'gasto_total' => (float) $user->tripsAsPassenger()->where('status', TripStatus::Completed)->sum('total'),
            'cancelados' => $user->tripsAsPassenger()->where('status', TripStatus::Cancelled)->count(),
            'favoritos' => $user->favoriteDrivers()->count(),
        ];

        $nombre = 'ficha_pasajero_'.$user->national_id.'_'.now()->format('Ymd').'.pdf';

        $pdf = Pdf::loadView('pdf.ficha-pasajero', [
            'title' => 'Ficha Técnica Oficial del Pasajero',
            'subtitle' => 'Expediente registrado en el sistema GAZ',
            'pasajero' => $user,
            'viajes' => $viajes,
            'resumen' => $resumen,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($nombre);
    }

    /** @param Builder<User> $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'activos' => $query->where('status', UserStatus::Approved),
            'en_espera' => $query->where('status', UserStatus::Pending),
            'inhabilitados' => $query->where('status', UserStatus::Suspended),
            'con_viajes' => $query->whereHas('tripsAsPassenger', fn (Builder $q) => $q->where('status', TripStatus::Completed)),
            'con_saldo' => $query->whereHas('wallet', fn (Builder $q) => $q->where('balance', '>', 0)),
            default => null,
        };
    }

    /** @return array<string, int> */
    private function contadores(): array
    {
        $base = fn (): Builder => User::query()->role(UserRole::Passenger);

        return [
            'todos' => $base()->count(),
            'activos' => $base()->where('status', UserStatus::Approved)->count(),
            'en_espera' => $base()->where('status', UserStatus::Pending)->count(),
            'inhabilitados' => $base()->where('status', UserStatus::Suspended)->count(),
            'con_viajes' => $base()->whereHas('tripsAsPassenger', fn (Builder $q) => $q->where('status', TripStatus::Completed))->count(),
            'con_saldo' => $base()->whereHas('wallet', fn (Builder $q) => $q->where('balance', '>', 0))->count(),
        ];
    }
}
