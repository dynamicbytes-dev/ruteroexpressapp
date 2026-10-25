@extends('pdf.layout')

@section('content')
    <table style="width: 100%; margin-bottom: 16px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 12px;">
                <div class="section-title">Datos Personales del Pasajero</div>
                <table class="table-data" style="margin-top: 4px;">
                    <tr>
                        <td style="width: 35%; font-weight: bold; background-color: #f8fafc;">Nombre completo:</td>
                        <td>{{ $pasajero->name }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Cédula de identidad:</td>
                        <td class="font-mono">{{ $pasajero->national_id }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Correo electrónico:</td>
                        <td>{{ $pasajero->email }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Teléfono:</td>
                        <td>{{ $pasajero->phone ?? 'No registrado' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Ciudad / Zona:</td>
                        <td>{{ $pasajero->city?->displayName() ?? 'Sin asignar' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Dirección:</td>
                        <td>{{ $pasajero->address ?? 'No registrada' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 12px;">
                <div class="section-title">Estado de Cuenta y Billetera</div>
                <table class="table-data" style="margin-top: 4px;">
                    <tr>
                        <td style="width: 40%; font-weight: bold; background-color: #f8fafc;">Estado de cuenta:</td>
                        <td>
                            @php
                                $estado = $pasajero->status?->value ?? (string) $pasajero->status;
                            @endphp
                            @if ($pasajero->isApproved())
                                <span class="badge badge-green">Aprobada / Activa</span>
                            @elseif ($pasajero->isSuspended())
                                <span class="badge badge-red">Inhabilitada / Suspendida</span>
                            @else
                                <span class="badge badge-yellow">{{ ucfirst($estado) }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Saldo disponible:</td>
                        <td class="font-mono" style="font-weight: bold; font-size: 13px; color: #1b0730;">
                            ${{ number_format((float) ($pasajero->wallet?->balance ?? 0), 2, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Saldo retenido:</td>
                        <td class="font-mono text-muted">
                            ${{ number_format((float) ($pasajero->wallet?->held_balance ?? 0), 2, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Fecha de registro:</td>
                        <td>{{ $pasajero->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold; background-color: #f8fafc;">Correo verificado:</td>
                        <td>{{ $pasajero->email_verified_at ? 'Sí ('.$pasajero->email_verified_at->format('d/m/Y').')' : 'Pendiente' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Métricas de viajes --}}
    <table class="metrics-grid">
        <tr>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Viajes completados</div>
                <div class="metric-val">{{ $resumen['completados'] }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Gasto total en viajes</div>
                <div class="metric-val">${{ number_format($resumen['gasto_total'], 2, ',', '.') }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Viajes cancelados</div>
                <div class="metric-val">{{ $resumen['cancelados'] }}</div>
            </td>
            <td class="metric-cell" style="width: 25%;">
                <div class="metric-label">Conductores favoritos</div>
                <div class="metric-val">{{ $resumen['favoritos'] }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Últimos viajes realizados por el pasajero</div>
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 8%;"># ID</th>
                <th style="width: 14%;">Fecha</th>
                <th style="width: 20%;">Conductor</th>
                <th style="width: 30%;">Trayecto (Origen &rarr; Destino)</th>
                <th style="width: 10%;" class="text-center">Método</th>
                <th style="width: 9%;" class="text-right">Total</th>
                <th style="width: 9%;" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($viajes as $viaje)
                <tr>
                    <td class="font-mono text-muted">#{{ $viaje->id }}</td>
                    <td>{{ $viaje->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $viaje->driver?->name ?? 'Sin asignar' }}</td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($viaje->origin_address, 18) }} &rarr; {{ \Illuminate\Support\Str::limit($viaje->destination_address, 18) }}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-purple">{{ $viaje->payment_method?->label() ?? 'Efectivo' }}</span>
                    </td>
                    <td class="text-right font-mono" style="font-weight: bold;">
                        ${{ number_format((float) $viaje->total, 2, ',', '.') }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $viaje->status->value === 'completed' ? 'badge-green' : ($viaje->status->value === 'cancelled' ? 'badge-red' : 'badge-blue') }}">
                            {{ $viaje->status->label() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 15px;">
                        Este pasajero aún no ha realizado viajes.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
