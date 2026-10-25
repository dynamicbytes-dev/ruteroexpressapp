<x-app-layout>
    <x-slot name="title">Reportes</x-slot>
    <x-slot name="subtitle">Resultados operativos y financieros del período</x-slot>

    <x-slot name="actions">
        <div class="flex items-center gap-2">
            <a href="{{ route('panel.reportes.viajes.pdf', ['desde' => $rango['desde']->toDateString(), 'hasta' => $rango['hasta']->toDateString()]) }}"
               class="btn-primary">
                <x-icon name="download" class="h-4 w-4" />
                Viajes PDF
            </a>
            <a href="{{ route('panel.reportes.movimientos.pdf', ['desde' => $rango['desde']->toDateString(), 'hasta' => $rango['hasta']->toDateString()]) }}"
               class="btn-primary">
                <x-icon name="download" class="h-4 w-4" />
                Movimientos PDF
            </a>
            <a href="{{ route('panel.reportes.viajes.csv', ['desde' => $rango['desde']->toDateString(), 'hasta' => $rango['hasta']->toDateString()]) }}"
               class="btn-outline text-xs" title="Exportar viajes en formato CSV">
                CSV
            </a>
        </div>
    </x-slot>

    <form method="GET" action="{{ route('panel.reportes.index') }}" class="card p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="desde" class="form-label">Desde</label>
                <input id="desde" name="desde" type="date" value="{{ $rango['desde']->toDateString() }}" class="form-input">
            </div>
            <div>
                <label for="hasta" class="form-label">Hasta</label>
                <input id="hasta" name="hasta" type="date" value="{{ $rango['hasta']->toDateString() }}" class="form-input">
            </div>
            <button type="submit" class="btn-primary">Actualizar</button>
            <a href="{{ route('panel.reportes.index') }}" class="btn-ghost">Mes actual</a>
            <span class="ml-auto text-xs text-ink-500">
                {{ $rango['desde']->translatedFormat('d M Y') }} — {{ $rango['hasta']->translatedFormat('d M Y') }}
            </span>
        </div>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Viajes completados', 'valor' => number_format($resumen['viajes'], 0, ',', '.'), 'detalle' => 'Ticket promedio $'.number_format($resumen['ticket'], 2, '.', ','), 'icono' => 'route'],
            ['label' => 'Facturación bruta', 'valor' => '$'.number_format($resumen['total'], 2, '.', ','), 'detalle' => 'Comisión $'.number_format($resumen['comision'], 2, '.', ','), 'icono' => 'wallet'],
            ['label' => 'Ganancia conductores', 'valor' => '$'.number_format($resumen['ganancia'], 2, '.', ','), 'detalle' => 'Liquidado en viajes', 'icono' => 'moto'],
            ['label' => 'Ajustes manuales', 'valor' => '$'.number_format($resumen['ajustes_neto'], 2, '.', ','), 'detalle' => $resumen['ajustes'].' movimiento(s)', 'icono' => 'exchange'],
        ] as $tarjeta)
            <div class="card flex items-start gap-4 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <x-icon :name="$tarjeta['icono']" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-ink-500">{{ $tarjeta['label'] }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-brand-900">{{ $tarjeta['valor'] }}</p>
                    <p class="mt-0.5 text-xs text-ink-500">{{ $tarjeta['detalle'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card-pad">
            <h2 class="panel-title">Formas de pago</h2>
            <p class="text-xs text-ink-500">Viajes completados en el período</p>

            <ul class="mt-5 space-y-4">
                @foreach ([['wallet', 'Billetera'], ['cash', 'Efectivo']] as [$clave, $etiqueta])
                    @php
                        $fila = $porMetodo->get($clave);
                        $cantidad = (int) ($fila->cantidad ?? 0);
                        $total = (float) ($fila->total ?? 0);
                        $porcentaje = $resumen['viajes'] > 0 ? round($cantidad / $resumen['viajes'] * 100) : 0;
                    @endphp
                    <li>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-ink-800">{{ $etiqueta }}</span>
                            <span class="text-ink-500">{{ $cantidad }} viaje(s) · ${{ number_format($total, 2, '.', ',') }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-100">
                            <div class="h-full rounded-full bg-brand-700" style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-ink-400">{{ $porcentaje }}% del total</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card-pad">
            <h2 class="panel-title">Dinero y usuarios</h2>
            <p class="text-xs text-ink-500">Entradas, salidas y registros nuevos</p>

            <dl class="mt-5 space-y-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-600">Recargas aprobadas</dt>
                    <dd class="text-right">
                        <span class="font-semibold text-brand-900">${{ number_format($resumen['recargas_usd'], 2, '.', ',') }}</span>
                        <span class="block text-xs text-ink-500">{{ $resumen['recargas'] }} operación(es)</span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-600">Retiros pagados</dt>
                    <dd class="text-right">
                        <span class="font-semibold text-brand-900">${{ number_format($resumen['retiros_usd'], 2, '.', ',') }}</span>
                        <span class="block text-xs text-ink-500">{{ $resumen['retiros'] }} operación(es)</span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-600">Pasajeros nuevos</dt>
                    <dd class="font-semibold text-brand-900">{{ $resumen['pasajeros_nuevos'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-ink-600">Conductores nuevos</dt>
                    <dd class="font-semibold text-brand-900">{{ $resumen['conductores_nuevos'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="card-pad">
            <h2 class="panel-title">Top conductores</h2>
            <p class="text-xs text-ink-500">Por viajes completados en el período</p>

            @if ($topConductores->isEmpty())
                <p class="mt-6 text-sm text-ink-500">Todavía no hay viajes completados.</p>
            @else
                <ol class="mt-5 space-y-4">
                    @foreach ($topConductores as $posicion => $fila)
                        <li class="flex items-start gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-800">
                                {{ $posicion + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink-900">{{ $fila->driver?->name ?? 'Conductor eliminado' }}</p>
                                <p class="text-xs text-ink-500">
                                    {{ (int) $fila->viajes }} viaje(s) · ${{ number_format((float) $fila->total, 2, '.', ',') }}
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-accent-800">
                                ${{ number_format((float) $fila->ganancia, 2, '.', ',') }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <section class="card mt-6">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4">
            <div>
                <h2 class="panel-title">Últimos viajes completados</h2>
                <p class="text-xs text-ink-500">Los 15 más recientes del período</p>
            </div>
            <a href="{{ route('panel.viajes.index', ['tab' => 'completed']) }}" class="btn-ghost py-1.5 text-xs">Ver todos</a>
        </header>

        @if ($ultimos->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-ink-500">
                No hay viajes completados en este período.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Viaje</th>
                            <th class="px-5 py-3 font-semibold">Finalizado</th>
                            <th class="px-5 py-3 font-semibold">Personas</th>
                            <th class="px-5 py-3 font-semibold">Pago</th>
                            <th class="px-5 py-3 font-semibold">Total</th>
                            <th class="px-5 py-3 font-semibold">Comisión</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($ultimos as $viaje)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3 font-medium text-ink-900">#{{ $viaje->id }}</td>
                                <td class="px-5 py-3 text-ink-500">{{ $viaje->completed_at?->translatedFormat('d M, H:i') }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-ink-800">{{ $viaje->passenger?->name ?? '—' }}</div>
                                    <div class="text-xs text-ink-500">{{ $viaje->driver?->name ?? 'Sin conductor' }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $viaje->payment_method?->label() }}</td>
                                <td class="px-5 py-3 font-semibold text-brand-800">${{ number_format((float) $viaje->total, 2, '.', ',') }}</td>
                                <td class="px-5 py-3 text-ink-600">${{ number_format((float) $viaje->commission_amount, 2, '.', ',') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.viajes.show', $viaje) }}" class="btn-outline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
