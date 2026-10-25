<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Trips\TripDispatcher;
use App\Domain\Verification\Actions\ReactivarCuenta;
use App\Domain\Verification\Actions\SuspenderCuenta;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\TripOffer;
use App\Models\User;
use App\Support\Configuracion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel de conductores del auditor.
 *
 * Es una vista de seguimiento: reúne disponibilidad, bloqueos, deuda,
 * documentos por vencer y actividad reciente. Las decisiones sobre documentos
 * y cuentas se siguen tomando desde la ficha del usuario.
 */
class DriverController extends Controller
{
    public const TABS = [
        'todos' => 'Todos',
        'en_linea' => 'En línea',
        'bloqueados' => 'Bloqueados',
        'inhabilitados' => 'Inhabilitados',
        'por_vencer' => 'Docs. por vencer',
        'deuda' => 'Saldo negativo',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS)
            ? (string) $request->query('tab')
            : 'todos';

        $busqueda = trim((string) $request->query('buscar', ''));
        $tope = Configuracion::decimal('tope_saldo_negativo', -5.0);

        $conductores = User::query()
            ->role(UserRole::Driver)
            ->with(['driverProfile.city', 'wallet'])
            ->withCount([
                'documents as expiring_documents_count' => fn (Builder $query) => $query
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '>=', today()->toDateString())
                    ->whereDate('expires_at', '<=', today()->addDays(30)->toDateString()),
                'documents as expired_documents_count' => fn (Builder $query) => $query
                    ->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', today()->toDateString()),
                'tripsAsDriver as completed_trips_count' => fn (Builder $query) => $query
                    ->where('status', TripStatus::Completed),
            ])
            ->withSum([
                'tripsAsDriver as earnings_sum' => fn (Builder $query) => $query
                    ->where('status', TripStatus::Completed),
            ], 'driver_earning')
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab, $tope))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $digitos = preg_replace('/\D/', '', $busqueda) ?? '';

                $query->where(function (Builder $sub) use ($busqueda, $digitos): void {
                    $sub->where('first_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('last_name', 'ilike', "%{$busqueda}%")
                        ->orWhere('email', 'ilike', "%{$busqueda}%")
                        ->orWhereHas('driverProfile', function (Builder $perfil) use ($busqueda): void {
                            $perfil->where('vehicle_plate', 'ilike', "%{$busqueda}%");
                        });

                    // Cédula y teléfono solo se buscan cuando el término es
                    // realmente numérico; así una placa no trae coincidencias
                    // accidentales por los dígitos que contiene.
                    if (strlen($digitos) >= 5) {
                        $sub->orWhere('national_id', 'like', "%{$digitos}%")
                            ->orWhere('phone', 'like', "%{$digitos}%");
                    }
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return view('panel.conductores.index', [
            'conductores' => $conductores,
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'contadores' => $this->contadores($tope),
            'tope' => $tope,
        ]);
    }

    public function show(User $user): View
    {
        abort_unless($user->isDriver(), 404);

        $user->load([
            'driverProfile.city',
            'wallet',
            'documents',
            'verificationReviews',
        ]);

        $viajes = $user->tripsAsDriver()
            ->with('passenger')
            ->latest('requested_at')
            ->limit(15)
            ->get();

        $ofertas = TripOffer::query()
            ->where('driver_id', $user->getKey())
            ->latest('offered_at')
            ->limit(15)
            ->get();

        $resumen = [
            'completados' => $user->tripsAsDriver()->where('status', TripStatus::Completed)->count(),
            'ganancias' => (float) $user->tripsAsDriver()->where('status', TripStatus::Completed)->sum('driver_earning'),
            'cancelados' => $user->tripsAsDriver()->where('status', TripStatus::Cancelled)->count(),
            'rechazos' => $user->tripOffers()->where('status', 'rejected')->count(),
            'vencidos' => $user->tripOffers()->where('status', 'expired')->count(),
            'favoritos' => $user->favoredByPassengers()->count(),
            'calificaciones' => (int) ($user->driverProfile?->ratings_count ?? 0),
        ];

        return view('panel.conductores.show', [
            'conductor' => $user,
            'viajes' => $viajes,
            'ofertas' => $ofertas,
            'resumen' => $resumen,
            'calificaciones' => $user->ratingsReceived()
                ->with('passenger')
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'coordenadas' => $user->driverProfile?->coordinates(),
            'tope' => Configuracion::decimal('tope_saldo_negativo', -5.0),
            'ciudades' => City::query()->active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Cambia la zona de operación del conductor. Solo el auditor puede hacerlo,
     * a solicitud del conductor. Se desconecta para que no conserve alertas de
     * la zona anterior.
     */
    public function cambiarZona(
        Request $request,
        User $user,
        TripDispatcher $asignador,
    ): RedirectResponse {
        abort_unless($user->isDriver(), 404);

        $datos = $request->validate([
            'city_id' => ['required', 'integer', 'exists:cities,id'],
        ], messages: [
            'city_id.required' => 'Elige la nueva zona del conductor.',
            'city_id.exists' => 'Esa ciudad no existe o no está disponible.',
        ]);

        $perfil = $user->driverProfile;

        if ($perfil === null) {
            return back()->with('error', 'Este conductor todavía no tiene perfil de vehículo.');
        }

        $ciudad = City::query()->active()->find($datos['city_id']);

        if ($ciudad === null) {
            return back()->with('error', 'Esa ciudad no está disponible para operar.');
        }

        if ((int) $perfil->city_id === (int) $ciudad->getKey()) {
            return back()->with('status', "{$user->name} ya opera en {$ciudad->name}.");
        }

        $anterior = City::query()->find($perfil->city_id);

        // Las alertas pendientes eran para la zona anterior: se cancelan y los
        // viajes siguen su curso normal con otro conductor de esa zona.
        $asignador->cancelarOfertaPendienteDeConductor($user);

        $perfil->forceFill([
            'city_id' => $ciudad->getKey(),
            'is_online' => false,
            'went_online_at' => null,
        ])->save();

        activity('conductores')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties([
                'zona_anterior' => $anterior?->name,
                'zona_nueva' => $ciudad->name,
            ])
            ->log('Zona de operación del conductor cambiada');

        return back()->with(
            'status',
            "Cambiaste la zona de {$user->name} a {$ciudad->displayName()}. Debe conectarse de nuevo para recibir viajes de esa zona.",
        );
    }

    /** El auditor inhabilita al conductor: deja de operar y se desconecta. */
    public function inhabilitar(
        Request $request,
        User $user,
        SuspenderCuenta $accion,
    ): RedirectResponse {
        abort_unless($user->isDriver(), 404);

        $datos = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], messages: [
            'reason.required' => 'Escribe el motivo: el conductor lo va a leer.',
            'reason.min' => 'Explica el motivo con un poco más de detalle (mínimo 10 caracteres).',
            'reason.max' => 'El motivo no puede pasar de 500 caracteres.',
        ]);

        $accion->ejecutar($user, $request->user(), $datos['reason']);

        return back()->with('status', "Inhabilitaste a {$user->name}. Ya no puede conectarse ni recibir viajes.");
    }

    /** Devuelve al conductor a la operación con los mismos documentos aprobados. */
    public function habilitar(
        Request $request,
        User $user,
        ReactivarCuenta $accion,
    ): RedirectResponse {
        abort_unless($user->isDriver(), 404);

        $accion->ejecutar($user, $request->user());

        return back()->with('status', "Habilitaste a {$user->name}. Ya puede volver a conectarse.");
    }

    /** @param  Builder<User>  $query */
    private function aplicarTab(Builder $query, string $tab, float $tope): void
    {
        match ($tab) {
            'en_linea' => $query->whereHas('driverProfile', fn (Builder $perfil) => $perfil->where('is_online', true)),
            'bloqueados' => $query->whereHas('driverProfile', fn (Builder $perfil) => $perfil
                ->whereNotNull('blocked_until')
                ->where('blocked_until', '>', now())),
            'inhabilitados' => $query->where('status', UserStatus::Suspended),
            'por_vencer' => $query->whereHas('documents', fn (Builder $documento) => $documento
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', today()->toDateString())
                ->whereDate('expires_at', '<=', today()->addDays(30)->toDateString())),
            'deuda' => $query->whereHas('wallet', fn (Builder $billetera) => $billetera->where('balance', '<', $tope)),
            default => null,
        };
    }

    /** @return array<string, int> */
    private function contadores(float $tope): array
    {
        $base = fn (): Builder => User::query()->role(UserRole::Driver);

        return [
            'todos' => $base()->count(),
            'en_linea' => $base()->whereHas('driverProfile', fn (Builder $perfil) => $perfil->where('is_online', true))->count(),
            'bloqueados' => $base()->whereHas('driverProfile', fn (Builder $perfil) => $perfil
                ->whereNotNull('blocked_until')
                ->where('blocked_until', '>', now()))->count(),
            'inhabilitados' => $base()->where('status', UserStatus::Suspended)->count(),
            'por_vencer' => $base()->whereHas('documents', fn (Builder $documento) => $documento
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', today()->toDateString())
                ->whereDate('expires_at', '<=', today()->addDays(30)->toDateString()))->count(),
            'deuda' => $base()->whereHas('wallet', fn (Builder $billetera) => $billetera->where('balance', '<', $tope))->count(),
        ];
    }
}
