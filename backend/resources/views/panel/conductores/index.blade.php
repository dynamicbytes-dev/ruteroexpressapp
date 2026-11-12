<x-app-layout>
    <x-slot name="title">Conductores</x-slot>
    <x-slot name="subtitle">Disponibilidad, bloqueos, documentos y saldo de los conductores</x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
            'slate' => 'badge-neutral',
        ];
    @endphp

    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? 0;
                $pendiente = in_array($clave, ['bloqueados', 'por_vencer', 'deuda'], true) && $total > 0;
            @endphp

            <a href="{{ route('panel.conductores.index', array_filter(['tab' => $clave, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white' : 'text-content-muted hover:bg-line hover:text-brand-800 dark:hover:text-content' }}">
                {{ $etiqueta }}
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                             {{ $activa ? 'bg-white/20 text-white' : ($pendiente ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300' : 'bg-line text-content-muted') }}">
                    {{ $total }}
                </span>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('panel.conductores.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-content-subtle">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Nombre, cédula, correo, teléfono o placa">
            </div>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '')
            <a href="{{ route('panel.conductores.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar</a>
        @endif
    </form>

    <section class="card mt-4">
        @if ($conductores->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="moto" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">No hay conductores en esta lista</p>
                <p class="mt-1 text-sm text-content-muted">
                    @if ($busqueda !== '')
                        Prueba con otra búsqueda o limpia el filtro.
                    @else
                        Los conductores aparecerán aquí en cuanto se registren.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Conductor</th>
                            <th class="px-5 py-3 font-semibold">Vehículo</th>
                            <th class="px-5 py-3 font-semibold">Zona</th>
                            <th class="px-5 py-3 font-semibold">Disponibilidad</th>
                            <th class="px-5 py-3 font-semibold">Billetera</th>
                            <th class="px-5 py-3 font-semibold">Documentos</th>
                            <th class="px-5 py-3 font-semibold">Viajes</th>
                            <th class="px-5 py-3 font-semibold">Calificación</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($conductores as $conductor)
                            @php
                                $perfil = $conductor->driverProfile;
                                $bloqueado = $perfil?->isBlocked() ?? false;
                                $deuda = (float) ($conductor->wallet?->balance ?? 0) < $tope;
                                $porVencer = (int) $conductor->expiring_documents_count;
                                $vencidos = (int) $conductor->expired_documents_count;
                            @endphp
                            <tr class="hover:bg-surface-alt/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-content">{{ $conductor->name }}</div>
                                    <div class="text-xs text-content-muted">V-{{ $conductor->national_id }} · {{ $conductor->email }}</div>
                                    <div class="mt-1">
                                        <span class="{{ $tintes[$conductor->status->tone()] ?? 'badge-neutral' }}">
                                            {{ $conductor->status->label() }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($perfil)
                                        <div class="font-medium text-content">{{ $perfil->vehicleTypeLabel() }}</div>
                                        <div class="text-xs text-content-muted">{{ $perfil->vehicle_brand }} {{ $perfil->vehicle_model }} {{ $perfil->vehicle_year }}</div>
                                        <div class="mt-1 font-mono text-xs font-semibold text-brand-800 dark:text-content">{{ $perfil->vehicle_plate }}</div>
                                    @else
                                        <span class="badge-neutral">Sin vehículo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($perfil?->city)
                                        <div class="font-medium text-content">{{ $perfil->city->name }}</div>
                                        <div class="text-xs text-content-muted">{{ $perfil->city->state }}</div>
                                    @else
                                        <span class="badge-pending">Sin zona</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($bloqueado)
                                        <span class="badge-rejected">Bloqueado</span>
                                        <div class="mt-1 text-xs text-content-muted">
                                            Hasta {{ $perfil->blocked_until->translatedFormat('d M, H:i') }}
                                        </div>
                                    @elseif ($perfil?->is_online)
                                        <span class="badge-approved">En línea</span>
                                        <div class="mt-1 text-xs text-content-muted">
                                            {{ $perfil->last_location_at?->diffForHumans() ?? 'Sin ubicación' }}
                                        </div>
                                    @else
                                        <span class="badge-neutral">Desconectado</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-semibold {{ $deuda ? 'text-red-700 dark:text-red-300' : 'text-brand-800 dark:text-content' }}">
                                        ${{ number_format((float) ($conductor->wallet?->balance ?? 0), 2, '.', ',') }}
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        Retenido: ${{ number_format((float) ($conductor->wallet?->held_balance ?? 0), 2, '.', ',') }}
                                    </div>
                                    @if ($deuda)
                                        <span class="badge-rejected mt-1">Bajo el tope</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($vencidos > 0)
                                        <span class="badge-rejected">{{ $vencidos }} vencido{{ $vencidos === 1 ? '' : 's' }}</span>
                                    @endif
                                    @if ($porVencer > 0)
                                        <span class="badge-pending">{{ $porVencer }} por vencer</span>
                                    @endif
                                    @if ($vencidos === 0 && $porVencer === 0)
                                        <span class="badge-approved">Al día</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-content">{{ (int) $conductor->completed_trips_count }} completados</div>
                                    <div class="text-xs text-content-muted">
                                        ${{ number_format((float) ($conductor->earnings_sum ?? 0), 2, '.', ',') }} generados
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @php
                                        $promedio = $perfil?->ratingOrNull();
                                    @endphp
                                    @if ($promedio !== null)
                                        <div class="font-semibold text-amber-600 dark:text-amber-300">★ {{ number_format($promedio, 1, '.', ',') }}</div>
                                        <div class="text-xs text-content-muted">
                                            {{ (int) $perfil->ratings_count }} calificación{{ (int) $perfil->ratings_count === 1 ? '' : 'es' }}
                                        </div>
                                    @else
                                        <span class="text-xs text-content-subtle">Sin calificaciones</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="flex flex-col items-end gap-2">
                                        <a href="{{ route('panel.conductores.show', $conductor) }}" class="btn-outline">Ver</a>

                                        @if ($conductor->status === \App\Enums\UserStatus::Suspended)
                                            <form method="POST" action="{{ route('panel.conductores.habilitar', $conductor) }}">
                                                @csrf
                                                <button type="submit" class="btn-accent py-1.5 text-xs">Habilitar</button>
                                            </form>
                                        @else
                                            <details class="text-left">
                                                <summary class="btn-ghost cursor-pointer py-1.5 text-xs marker:content-none">Inhabilitar</summary>
                                                <form method="POST" action="{{ route('panel.conductores.inhabilitar', $conductor) }}" class="mt-2 w-60">
                                                    @csrf
                                                    <textarea name="reason" rows="2" required minlength="10" maxlength="500"
                                                              class="form-input text-xs"
                                                              placeholder="Motivo: lo va a leer el conductor"></textarea>
                                                    <button type="submit" class="btn-danger mt-2 w-full py-1.5 text-xs">Confirmar</button>
                                                </form>
                                            </details>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($conductores->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $conductores->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
