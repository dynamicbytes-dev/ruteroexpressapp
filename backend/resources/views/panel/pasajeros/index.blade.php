<x-app-layout>
    <x-slot name="title">Pasajeros</x-slot>
    <x-slot name="subtitle">Gestión de cuentas, saldo, viajes, estados y fichas de pasajeros</x-slot>

    {{-- Pestañas de estado --}}
    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? 0;
                $alerta = in_array($clave, ['en_espera', 'inhabilitados'], true) && $total > 0;
            @endphp

            <a href="{{ route('panel.pasajeros.index', array_filter(['tab' => $clave, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white shadow-sm' : 'text-content-muted hover:bg-line hover:text-brand-800 dark:hover:text-content' }}">
                {{ $etiqueta }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                             {{ $activa ? 'bg-white/20 text-white' : ($alerta ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300' : 'bg-line text-content-muted') }}">
                    {{ $total }}
                </span>
            </a>
        @endforeach
    </nav>

    {{-- Buscador y filtros --}}
    <form method="GET" action="{{ route('panel.pasajeros.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar pasajero</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-content-subtle">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Nombre, cédula, correo o teléfono">
            </div>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '')
            <a href="{{ route('panel.pasajeros.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar búsqueda</a>
        @endif
    </form>

    {{-- Listado de pasajeros --}}
    <section class="card mt-4 overflow-hidden">
        @if ($pasajeros->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="users" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">No hay pasajeros en esta lista</p>
                <p class="mt-1 text-sm text-content-muted">
                    @if ($busqueda !== '')
                        No se encontraron pasajeros con el término "{{ $busqueda }}".
                    @else
                        Los pasajeros registrados aparecerán en esta sección.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Pasajero</th>
                            <th class="px-5 py-3 font-semibold">Ciudad / Zona</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3 font-semibold">Billetera</th>
                            <th class="px-5 py-3 font-semibold">Viajes</th>
                            <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($pasajeros as $pasajero)
                            <tr class="hover:bg-surface-alt/60 transition">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40 font-display text-sm font-bold text-brand-800 dark:text-content">
                                            {{ strtoupper(substr($pasajero->first_name ?: 'P', 0, 1)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('panel.pasajeros.show', $pasajero) }}"
                                               class="font-display font-semibold text-brand-900 dark:text-content hover:text-brand-700 dark:hover:text-accent-300 hover:underline">
                                                {{ $pasajero->name }}
                                            </a>
                                            <div class="text-xs text-content-muted">
                                                C.I. {{ $pasajero->national_id }} · {{ $pasajero->phone ?? 'Sin telf.' }}
                                            </div>
                                            <div class="text-[11px] text-content-subtle">{{ $pasajero->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-content-muted">
                                    {{ $pasajero->city?->displayName() ?? 'Sin asignar' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($pasajero->isApproved())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-950 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Aprobado
                                        </span>
                                    @elseif ($pasajero->isSuspended())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-950 px-2.5 py-1 text-xs font-medium text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Inhabilitado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300 border border-amber-200">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            {{ $pasajero->status->label() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="font-display font-semibold {{ ($pasajero->wallet?->balance ?? 0) > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-content-muted' }}">
                                        ${{ number_format((float) ($pasajero->wallet?->balance ?? 0), 2, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="font-medium text-content">
                                        {{ $pasajero->completed_trips_count ?? 0 }} completados
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        ${{ number_format((float) ($pasajero->spent_sum ?? 0), 2, ',', '.') }} gastados
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('panel.pasajeros.show', $pasajero) }}"
                                           class="btn-outline btn-sm"
                                           title="Ver expediente completo">
                                            Ver
                                        </a>

                                        <a href="{{ route('panel.pasajeros.edit', $pasajero) }}"
                                           class="btn-outline btn-sm text-content-muted hover:text-brand-800 dark:hover:text-content"
                                           title="Editar datos del pasajero">
                                            <x-icon name="pencil" class="h-3.5 w-3.5" />
                                            Editar
                                        </a>

                                        <a href="{{ route('panel.pasajeros.pdf', $pasajero) }}"
                                           class="btn-outline btn-sm text-brand-800 dark:text-content hover:bg-brand-50 dark:hover:bg-brand-900/30"
                                           title="Exportar ficha oficial en PDF">
                                            <x-icon name="download" class="h-3.5 w-3.5" />
                                            Ficha PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($pasajeros->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $pasajeros->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
