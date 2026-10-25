@extends('pdf.layout')

@section('content')
    {{-- Tarjetas de resumen --}}
    <table class="metrics-grid">
        <tr>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Total de viajes</div>
                <div class="metric-val">{{ number_format($resumen['cantidad'], 0, ',', '.') }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Facturación total</div>
                <div class="metric-val">${{ number_format($resumen['total'], 2, ',', '.') }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Comisión GAZ</div>
                <div class="metric-val">${{ number_format($resumen['comision'], 2, ',', '.') }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Ganancia Conductores</div>
                <div class="metric-val">${{ number_format($resumen['ganancia'], 2, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Detalle de viajes completados en el período</div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 6%;"># ID</th>
                <th style="width: 13%;">Fecha y Hora</th>
                <th style="width: 17%;">Pasajero</th>
                <th style="width: 17%;">Conductor</th>
                <th style="width: 21%;">Ruta (Origen &rarr; Destino)</th>
                <th style="width: 8%;" class="text-center">Pago</th>
                <th style="width: 9%;" class="text-right">Total</th>
                <th style="width: 9%;" class="text-right">Comisión</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($viajes as $viaje)
                <tr>
                    <td class="font-mono text-muted">#{{ $viaje->id }}</td>
                    <td>{{ $viaje->completed_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <strong>{{ $viaje->passenger?->name ?? 'Pasajero' }}</strong>
                    </td>
                    <td>
                        {{ $viaje->driver?->name ?? 'Conductor' }}
                    </td>
                    <td>
                        <span title="{{ $viaje->origin_address }}">{{ \Illuminate\Support\Str::limit($viaje->origin_address, 20) }}</span>
                        &rarr;
                        <span title="{{ $viaje->destination_address }}">{{ \Illuminate\Support\Str::limit($viaje->destination_address, 20) }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-purple">{{ $viaje->payment_method?->label() ?? 'Efectivo' }}</span>
                    </td>
                    <td class="text-right font-mono" style="font-weight: bold;">
                        ${{ number_format((float) $viaje->total, 2, ',', '.') }}
                    </td>
                    <td class="text-right font-mono text-muted">
                        ${{ number_format((float) $viaje->commission_amount, 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 20px;">
                        No se registraron viajes completados en este rango de fechas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
