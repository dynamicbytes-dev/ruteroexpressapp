<x-app-layout>
    <x-slot name="title">Viajes</x-slot>
    <x-slot name="subtitle">Seguimiento de viajes pedidos desde la app</x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'purple' => 'badge-neutral',
            'cyan' => 'badge-approved',
            'green' => 'badge-approved',
            'slate' => 'badge-neutral',
        ];
    @endphp

    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? 0;
            @endphp

            <a href="{{ route('panel.viajes.index', array_filter(['tab' => $clave, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white' : 'text-ink-600 hover:bg-ink-100 hover:text-brand-800' }}">
                {{ $etiqueta }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                             {{ $activa ? 'bg-white/20 text-white' : (($clave === 'active' && $total > 0) ? 'bg-amber-100 text-amber-800' : 'bg-ink-100 text-ink-500') }}">
                    {{ $total }}
                </span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('panel.viajes.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Número, persona o dirección">
            </div>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '')
            <a href="{{ route('panel.viajes.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar</a>
        @endif
    </form>

    <section class="card mt-4">
        @if ($viajes->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400">
                    <x-icon name="route" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900">No hay viajes en esta lista</p>
                <p class="mt-1 text-sm text-ink-500">
                    @if ($busqueda !== '')
                        Prueba con otra búsqueda o limpia el filtro.
                    @else
                        Los viajes aparecerán aquí en cuanto los pasajeros empiecen a pedir.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Viaje</th>
                            <th class="px-5 py-3 font-semibold">Ruta</th>
                            <th class="px-5 py-3 font-semibold">Personas</th>
                            <th class="px-5 py-3 font-semibold">Cobro</th>
                            <th class="px-5 py-3 font-semibold">Pedido</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($viajes as $viaje)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">#{{ $viaje->id }}</div>
                                    <div class="text-xs text-ink-500">{{ $viaje->payment_method->label() }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="max-w-64 truncate font-medium text-ink-900">{{ $viaje->origin_address }}</div>
                                    <div class="max-w-64 truncate text-xs text-ink-500">→ {{ $viaje->destination_address }}</div>
                                    <div class="text-xs text-ink-400">
                                        {{ number_format((float) $viaje->distance_km, 2, ',', '.') }} km ·
                                        {{ $viaje->duration_minutes }} min
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $viaje->passenger?->name ?? '—' }}</div>
                                    <div class="text-xs text-ink-500">
                                        Conductor: {{ $viaje->driver?->name ?? 'Sin asignar' }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-semibold text-brand-800">${{ number_format((float) $viaje->total, 2, '.', ',') }}</div>
                                    <div class="text-xs text-ink-500">
                                        Conductor: ${{ number_format((float) $viaje->driver_earning, 2, '.', ',') }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-ink-500">
                                    {{ $viaje->requested_at?->translatedFormat('d M, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $tintes[$viaje->status->tone()] ?? 'badge-neutral' }}">
                                        {{ $viaje->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.viajes.show', $viaje) }}" class="btn-outline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($viajes->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">
                    {{ $viajes->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
