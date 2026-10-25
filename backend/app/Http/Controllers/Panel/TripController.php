<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Seguimiento de viajes para el auditor. Es una vista de solo lectura: el
 * auditor no cambia estados ni cobra; solo revisa qué pasó y con quién.
 */
class TripController extends Controller
{
    public const TABS = [
        'active' => 'En curso',
        'completed' => 'Completados',
        'cancelled' => 'Cancelados',
        'all' => 'Todos',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS)
            ? (string) $request->query('tab')
            : 'active';

        $busqueda = trim((string) $request->query('buscar', ''));

        $viajes = Trip::query()
            ->with(['passenger', 'driver'])
            ->tap(fn (Builder $query) => $this->aplicarTab($query, $tab))
            ->when($busqueda !== '', function (Builder $query) use ($busqueda): void {
                $query->where(function (Builder $sub) use ($busqueda): void {
                    $sub->where('origin_address', 'ilike', "%{$busqueda}%")
                        ->orWhere('destination_address', 'ilike', "%{$busqueda}%");

                    if (ctype_digit($busqueda)) {
                        $sub->orWhere('id', (int) $busqueda);
                    }

                    $sub->orWhereHas('passenger', function (Builder $persona) use ($busqueda): void {
                        $persona->where('first_name', 'ilike', "%{$busqueda}%")
                            ->orWhere('last_name', 'ilike', "%{$busqueda}%")
                            ->orWhere('email', 'ilike', "%{$busqueda}%");
                    })
                        ->orWhereHas('driver', function (Builder $persona) use ($busqueda): void {
                            $persona->where('first_name', 'ilike', "%{$busqueda}%")
                                ->orWhere('last_name', 'ilike', "%{$busqueda}%")
                                ->orWhere('email', 'ilike', "%{$busqueda}%");
                        });
                });
            })
            ->latest('requested_at')
            ->paginate(20)
            ->withQueryString();

        $contadores = [
            'active' => Trip::query()->active()->count(),
            'completed' => Trip::query()->where('status', TripStatus::Completed)->count(),
            'cancelled' => Trip::query()->whereIn('status', [TripStatus::Cancelled, TripStatus::NoDriver])->count(),
            'all' => Trip::query()->count(),
        ];

        return view('panel.viajes.index', [
            'tabs' => self::TABS,
            'tab' => $tab,
            'busqueda' => $busqueda,
            'viajes' => $viajes,
            'contadores' => $contadores,
        ]);
    }

    public function show(Trip $trip): View
    {
        $trip->load([
            'passenger',
            'driver.driverProfile',
            'offers.driver',
        ]);

        return view('panel.viajes.show', [
            'viaje' => $trip,
        ]);
    }

    /** @param  Builder<Trip>  $query */
    private function aplicarTab(Builder $query, string $tab): void
    {
        match ($tab) {
            'active' => $query->active(),
            'completed' => $query->where('status', TripStatus::Completed),
            'cancelled' => $query->whereIn('status', [TripStatus::Cancelled, TripStatus::NoDriver]),
            default => null,
        };
    }
}
