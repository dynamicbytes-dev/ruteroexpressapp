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
            <span class="ml-auto text-xs text-content-muted">
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
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-accent-300">
                    <x-icon :name="$tarjeta['icono']" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-content-muted">{{ $tarjeta['label'] }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold text-brand-900 dark:text-content">{{ $tarjeta['valor'] }}</p>
                    <p class="mt-0.5 text-xs text-content-muted">{{ $tarjeta['detalle'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card-pad">
            <h2 class="panel-title">Formas de pago</h2>
            <p class="text-xs text-content-muted">Viajes completados en el período</p>

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
                            <span class="font-medium text-content">{{ $etiqueta }}</span>
                            <span class="text-content-muted">{{ $cantidad }} viaje(s) · ${{ number_format($total, 2, '.', ',') }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-line">
                            <div class="h-full rounded-full bg-brand-700" style="width: {{ $porcentaje }}%"></div>
                        </div>
                        <p class="mt-1 text-xs text-content-subtle">{{ $porcentaje }}% del total</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card-pad">
            <h2 class="panel-title">Dinero y usuarios</h2>
            <p class="text-xs text-content-muted">Entradas, salidas y registros nuevos</p>

            <dl class="mt-5 space-y-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-content-muted">Recargas aprobadas</dt>
                    <dd class="text-right">
                        <span class="font-semibold text-brand-900 dark:text-content">${{ number_format($resumen['recargas_usd'], 2, '.', ',') }}</span>
                        <span class="block text-xs text-content-muted">{{ $resumen['recargas'] }} operación(es)</span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-content-muted">Retiros pagados</dt>
                    <dd class="text-right">
                        <span class="font-semibold text-brand-900 dark:text-content">${{ number_format($resumen['retiros_usd'], 2, '.', ',') }}</span>
                        <span class="block text-xs text-content-muted">{{ $resumen['retiros'] }} operación(es)</span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-content-muted">Pasajeros nuevos</dt>
                    <dd class="font-semibold text-brand-900 dark:text-content">{{ $resumen['pasajeros_nuevos'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-content-muted">Conductores nuevos</dt>
                    <dd class="font-semibold text-brand-900 dark:text-content">{{ $resumen['conductores_nuevos'] }}</dd>
                </div>
            </dl>
        </section>

        <section class="card-pad">
            <h2 class="panel-title">Top conductores</h2>
            <p class="text-xs text-content-muted">Por viajes completados en el período</p>

            @if ($topConductores->isEmpty())
                <p class="mt-6 text-sm text-content-muted">Todavía no hay viajes completados.</p>
            @else
                <ol class="mt-5 space-y-4">
                    @foreach ($topConductores as $posicion => $fila)
                        <li class="flex items-start gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-900/30 text-xs font-semibold text-brand-800 dark:text-content">
                                {{ $posicion + 1 }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-content">{{ $fila->driver?->name ?? 'Conductor eliminado' }}</p>
                                <p class="text-xs text-content-muted">
                                    {{ (int) $fila->viajes }} viaje(s) · ${{ number_format((float) $fila->total, 2, '.', ',') }}
                                </p>
                            </div>
                            <span class="text-xs font-semibold text-accent-800 dark:text-accent-300">
                                ${{ number_format((float) $fila->ganancia, 2, '.', ',') }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    <section class="card mt-6">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
            <div>
                <h2 class="panel-title">Últimos viajes completados</h2>
                <p class="text-xs text-content-muted">Los 15 más recientes del período</p>
            </div>
            <a href="{{ route('panel.viajes.index', ['tab' => 'completed']) }}" class="btn-ghost py-1.5 text-xs">Ver todos</a>
        </header>

        @if ($ultimos->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-content-muted">
                No hay viajes completados en este período.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
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
                    <tbody class="divide-y divide-line">
                        @foreach ($ultimos as $viaje)
                            <tr class="hover:bg-surface-alt/60">
                                <td class="px-5 py-3 font-medium text-content">#{{ $viaje->id }}</td>
                                <td class="px-5 py-3 text-content-muted">{{ $viaje->completed_at?->translatedFormat('d M, H:i') }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-content">{{ $viaje->passenger?->name ?? '—' }}</div>
                                    <div class="text-xs text-content-muted">{{ $viaje->driver?->name ?? 'Sin conductor' }}</div>
                                </td>
                                <td class="px-5 py-3 text-content-muted">{{ $viaje->payment_method?->label() }}</td>
                                <td class="px-5 py-3 font-semibold text-brand-800 dark:text-content">${{ number_format((float) $viaje->total, 2, '.', ',') }}</td>
                                <td class="px-5 py-3 text-content-muted">${{ number_format((float) $viaje->commission_amount, 2, '.', ',') }}</td>
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
