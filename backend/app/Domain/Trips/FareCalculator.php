<?php

namespace App\Domain\Trips;

use App\Enums\VehicleType;
use App\Support\Configuracion;
use Carbon\CarbonInterface;

/**
 * Calcula la tarifa de un viaje con los valores configurados por el auditor.
 *
 * La app nunca hace esta cuenta: el backend devuelve el desglose y la app lo
 * muestra tal cual. Los valores quedan congelados en el viaje (`fare_breakdown`)
 * para que un cambio de tarifas no altere viajes anteriores.
 */
class FareCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calcular(
        float $distanciaKm,
        int $duracionMinutos,
        VehicleType $tipo = VehicleType::Motorcycle,
        ?CarbonInterface $momento = null,
        ?int $cityId = null,
    ): array {
        $momento ??= now();

        $distanciaKm = max(0, round($distanciaKm, 2));
        $duracionMinutos = max(1, $duracionMinutos);

        $base = Configuracion::decimal('tarifa_base', 0.00, $cityId);
        $porKm = Configuracion::decimal('precio_km', 1.00, $cityId);
        $porMinuto = Configuracion::decimal('precio_minuto', 0.00, $cityId);
        $minima = Configuracion::decimal('tarifa_minima', 1.00, $cityId);

        // Tarifa por distancia exacta (1$ por km) + base y tiempo si aplican
        $costoKm = round($distanciaKm * $porKm, 2);
        $costoTiempo = round($duracionMinutos * $porMinuto, 2);
        $calculado = round($base + $costoKm + $costoTiempo, 2);
        $subtotal = max($calculado, $minima);
        $minimaAplicada = $subtotal > $calculado;

        [$nocturno, $porcentajeNocturno] = $this->recargoNocturno($momento, $cityId);

        // 3. Recargo nocturno: 20% aplicado únicamente sobre el subtotal del viaje
        $recargoNocturno = $nocturno && $porcentajeNocturno > 0
            ? round($subtotal * ($porcentajeNocturno / 100), 2)
            : 0.0;

        $dinamicaMin = Configuracion::decimal('dinamica_min', 1.0, $cityId);
        $dinamicaMax = Configuracion::decimal('dinamica_max', 1.8, $cityId);
        $dinamica = Configuracion::decimal('dinamica_actual', 1.0, $cityId);
        $dinamica = min(max($dinamica, $dinamicaMin), max($dinamicaMin, $dinamicaMax));

        // Si la tarifa dinámica está activa (> 1.0), el incremento se calcula sobre el subtotal base
        $recargoDinamica = $dinamica > 1.0
            ? round($subtotal * ($dinamica - 1.0), 2)
            : 0.0;

        $subtotalConRecargos = round($subtotal + $recargoNocturno + $recargoDinamica, 2);

        // Recargo de vehículo: carro cuesta 1$ más que moto
        $recargoVehiculo = $tipo === VehicleType::Car
            ? Configuracion::decimal('recargo_carro_usd', 1.00, $cityId)
            : 0.0;

        $total = round($subtotalConRecargos + $recargoVehiculo, 2);
        $comisionPorcentaje = Configuracion::decimal('comision_porcentaje', 10, $cityId);
        $comision = round($total * ($comisionPorcentaje / 100), 2);
        $ganancia = round($total - $comision, 2);

        return [
            'city_id' => $cityId,
            'vehicle_type' => $tipo->value,
            'vehicle_type_label' => $tipo->label(),
            'vehicle_surcharge' => $recargoVehiculo,
            'distance_km' => $distanciaKm,
            'duration_minutes' => $duracionMinutos,
            'base_fare' => $base,
            'price_per_km' => $porKm,
            'price_per_minute' => $porMinuto,
            'minimum_fare' => $minima,
            'minimum_applied' => $minimaAplicada,
            'dynamic_multiplier' => round($dinamica, 2),
            'night_surcharge_percent' => $porcentajeNocturno,
            'night_surcharge_amount' => $recargoNocturno,
            'night' => $nocturno,
            'subtotal' => round($subtotal, 2),
            'total' => $total,
            'commission_percent' => $comisionPorcentaje,
            'commission_amount' => $comision,
            'driver_earning' => $ganancia,
            'calculated_at' => $momento->toIso8601String(),
        ];
    }

    /**
     * La franja nocturna puede cruzar la medianoche (por defecto 19:00–06:00).
     *
     * @return array{0: bool, 1: float}
     */
    private function recargoNocturno(CarbonInterface $momento, ?int $cityId = null): array
    {
        $desde = (string) Configuracion::get('nocturno_desde', '19:00', $cityId);
        $hasta = (string) Configuracion::get('nocturno_hasta', '06:00', $cityId);
        $hora = $momento->format('H:i');

        $dentro = $desde <= $hasta
            ? $hora >= $desde && $hora < $hasta
            : $hora >= $desde || $hora < $hasta;

        return [
            $dentro,
            $dentro ? Configuracion::decimal('recargo_nocturno_porcentaje', 20, $cityId) : 0.0,
        ];
    }
}
