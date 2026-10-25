<?php

namespace App\Http\Controllers\Api;

use App\Domain\Trips\TripService;
use App\Domain\Wallet\OperacionNoPermitidaException;
use App\Domain\Wallet\SaldoInsuficienteException;
use App\Enums\TripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelTripRequest;
use App\Http\Requests\Api\QuoteTripRequest;
use App\Http\Requests\Api\StoreTripRequest;
use App\Http\Requests\Api\TripHistoryRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Viajes del pasajero y acciones compartidas del viaje. */
class TripController extends Controller
{
    public function __construct(private readonly TripService $viajes) {}

    public function cotizar(QuoteTripRequest $request): JsonResponse
    {
        $this->soloPasajero($request);

        return response()->json([
            'data' => $this->viajes->cotizar(
                $request->validated(),
                $request->user()->city_id,
                $request->user(),
            ),
        ]);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $this->soloPasajero($request);

        try {
            $viaje = $this->viajes->crear($request->user(), $request->validated());
        } catch (SaldoInsuficienteException $error) {
            return response()->json(['message' => $error->getMessage()], 400);
        } catch (OperacionNoPermitidaException|RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => $viaje->status === TripStatus::NoDriver
                ? 'No encontramos conductores cerca. Puedes intentarlo de nuevo.'
                : 'Estamos buscando un conductor para tu viaje.',
            'data' => ['trip' => TripResource::make($viaje)],
        ], 201);
    }

    public function index(TripHistoryRequest $request): JsonResponse
    {
        $usuario = $request->user();

        $desde = match ($request->validated('period')) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(Carbon::MONDAY),
            'month' => now()->startOfMonth(),
            default => null,
        };

        $viajes = Trip::query()
            ->when($usuario->isPassenger(), fn ($query) => $query->forPassenger($usuario))
            ->when($usuario->isDriver(), fn ($query) => $query->forDriver($usuario))
            ->when($desde !== null, fn ($query) => $query->historySince($desde))
            ->with(['passenger', 'driver.driverProfile', 'currentOffer', 'rating', 'city'])
            ->latest()
            ->paginate(20);

        return TripResource::collection($viajes)->response();
    }

    public function activo(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $viaje = Trip::query()
            ->when($usuario->isPassenger(), fn ($query) => $query->forPassenger($usuario))
            ->when($usuario->isDriver(), fn ($query) => $query->forDriver($usuario))
            ->active()
            ->with(['passenger', 'driver.driverProfile', 'currentOffer', 'rating', 'city'])
            ->latest()
            ->first();

        return response()->json([
            'data' => ['trip' => $viaje === null ? null : TripResource::make($viaje)],
        ]);
    }

    public function show(Request $request, Trip $trip): JsonResponse
    {
        $this->autorizar($request, $trip);
        $trip->load(['passenger', 'driver.driverProfile', 'currentOffer', 'rating']);

        return response()->json([
            'data' => ['trip' => TripResource::make($trip)],
        ]);
    }

    public function cancelar(CancelTripRequest $request, Trip $trip): JsonResponse
    {
        $this->autorizar($request, $trip);

        try {
            $viaje = $this->viajes->cancelar(
                $trip,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => $viaje->status === TripStatus::Cancelled
                ? 'El viaje fue cancelado.'
                : 'El viaje volvió a búsqueda de conductor.',
            'data' => ['trip' => TripResource::make($viaje)],
        ]);
    }

    public function llegada(Request $request, Trip $trip): JsonResponse
    {
        return $this->accionConductor($request, $trip, 'llegada');
    }

    public function esperando(Request $request, Trip $trip): JsonResponse
    {
        return $this->accionConductor($request, $trip, 'esperando');
    }

    public function iniciar(Request $request, Trip $trip): JsonResponse
    {
        return $this->accionConductor($request, $trip, 'iniciar');
    }

    public function finalizar(Request $request, Trip $trip): JsonResponse
    {
        return $this->accionConductor($request, $trip, 'finalizar');
    }

    public function pagar(Request $request, Trip $trip): JsonResponse
    {
        return $this->accionConductor($request, $trip, 'finalizar');
    }

    private function accionConductor(Request $request, Trip $trip, string $accion): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->isDriver() || $trip->driver_id !== $usuario->getKey()) {
            return response()->json(['message' => 'Este viaje no está asignado a ti.'], 403);
        }

        try {
            $viaje = match ($accion) {
                'llegada' => $this->viajes->marcarLlegada($trip, $usuario),
                'esperando' => $this->viajes->marcarEsperando($trip, $usuario),
                'iniciar' => $this->viajes->iniciar($trip, $usuario),
                'finalizar' => $this->viajes->finalizar($trip, $usuario),
            };
        } catch (SaldoInsuficienteException $error) {
            return response()->json(['message' => $error->getMessage()], 400);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        $mensajes = [
            'llegada' => 'Avisamos al pasajero que vas en camino.',
            'esperando' => 'Avisamos al pasajero que ya estás en el punto de recogida.',
            'iniciar' => 'El viaje comenzó. Buen camino.',
            'finalizar' => $viaje->payment_method->value === 'wallet'
                ? 'Viaje completado. El pago se descontó de la billetera.'
                : 'Viaje completado. Recuerda cobrar en efectivo y cubrir la comisión.',
        ];

        return response()->json([
            'message' => $mensajes[$accion],
            'data' => ['trip' => TripResource::make($viaje->load(['passenger', 'driver.driverProfile', 'currentOffer', 'rating']))],
        ]);
    }

    private function soloPasajero(Request $request): void
    {
        abort_unless($request->user()->isPassenger(), 403, 'Solo los pasajeros pueden pedir viajes.');
        abort_unless($request->user()->canOperate(), 403, 'Tu cuenta todavía no está habilitada para pedir viajes.');
    }

    private function autorizar(Request $request, Trip $trip): void
    {
        abort_unless(
            $trip->belongsToUser($request->user()),
            403,
            'Este viaje no es tuyo.',
        );
    }
}
