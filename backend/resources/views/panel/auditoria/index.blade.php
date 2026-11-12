<x-app-layout>
    <x-slot name="title">Historial de actividades</x-slot>
    <x-slot name="subtitle">Registro de auditoría y trazabilidad inmutable del sistema GAZ</x-slot>

    <x-slot name="actions">
        <div class="flex items-center gap-2">
            <a href="{{ route('panel.historial.export.pdf', request()->query()) }}" class="btn-primary">
                <x-icon name="download" class="h-4 w-4" />
                Exportar PDF
            </a>
            <a href="{{ route('panel.historial.export', request()->query()) }}" class="btn-outline">
                <x-icon name="download" class="h-4 w-4" />
                Exportar CSV
            </a>
        </div>
    </x-slot>

    @php
        $modulos = [
            'pasajeros' => 'Pasajeros',
            'usuario' => 'Usuarios',
            'conductores' => 'Conductores',
            'recargas' => 'Recargas',
            'retiros' => 'Retiros',
            'viajes' => 'Viajes',
            'cupones' => 'Cupones',
            'banners' => 'Publicidad',
            'tasa_cambio' => 'Tasa de cambio',
            'configuracion' => 'Configuración',
            'billetera' => 'Billetera',
        ];
        $tonosEvento = [
            'created' => 'badge-approved',
            'updated' => 'badge-pending',
            'deleted' => 'badge-rejected',
        ];
    @endphp

    <form method="GET" action="{{ route('panel.auditoria.index') }}" class="card p-4">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label for="q" class="form-label">Buscar</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-content-subtle">
                        <x-icon name="search" class="h-5 w-5" />
                    </span>
                    <input id="q" name="q" type="search" value="{{ $filtros['q'] }}"
                           class="form-input pl-10" placeholder="Descripción, persona o correo">
                </div>
            </div>

            <div>
                <label for="log" class="form-label">Módulo</label>
                <select id="log" name="log" class="form-input pr-8">
                    <option value="">Todos</option>
                    @foreach ($logs as $log)
                        <option value="{{ $log }}" @selected($filtros['log'] === $log)>
                            {{ $modulos[$log] ?? ucfirst(str_replace('_', ' ', $log)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="evento" class="form-label">Acción</label>
                <select id="evento" name="evento" class="form-input pr-8">
                    <option value="">Todas</option>
                    @foreach ($eventos as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected($filtros['evento'] === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="desde" class="form-label">Desde</label>
                    <input id="desde" name="desde" type="date" value="{{ $filtros['desde'] }}" class="form-input">
                </div>
                <div>
                    <label for="hasta" class="form-label">Hasta</label>
                    <input id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] }}" class="form-input">
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="{{ route('panel.auditoria.index') }}" class="btn-ghost">Limpiar</a>
            <span class="ml-auto text-xs text-content-muted">{{ number_format($total, 0, ',', '.') }} registro(s)</span>
        </div>
    </form>

    <section class="card mt-4">
        @if ($actividades->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="list" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">No hay registros con estos filtros</p>
                <p class="mt-1 text-sm text-content-muted">Prueba con otro rango de fechas o limpia la búsqueda.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Fecha</th>
                            <th class="px-5 py-3 font-semibold">Autor</th>
                            <th class="px-5 py-3 font-semibold">Acción</th>
                            <th class="px-5 py-3 font-semibold">Módulo</th>
                            <th class="px-5 py-3 font-semibold">Descripción</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($actividades as $actividad)
                            <tr class="align-top hover:bg-surface-alt/60">
                                <td class="whitespace-nowrap px-5 py-3 text-content-muted">
                                    {{ $actividad->created_at?->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    @if ($actividad->causer instanceof \App\Models\User)
                                        <div class="font-medium text-content">{{ $actividad->causer->name }}</div>
                                        <div class="text-xs text-content-muted">{{ $actividad->causer->email }}</div>
                                    @else
                                        <span class="badge-neutral">Sistema</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $tonosEvento[$actividad->event] ?? 'badge-neutral' }}">
                                        {{ $eventos[$actividad->event] ?? ($actividad->event ?: 'Acción') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-content-muted">
                                    {{ $modulos[$actividad->log_name] ?? ucfirst(str_replace('_', ' ', (string) $actividad->log_name)) }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="max-w-xl text-content">{{ $actividad->description }}</div>
                                    @if ($actividad->subject)
                                        <div class="mt-1 text-xs text-content-muted">
                                            {{ class_basename((string) $actividad->subject_type) }} #{{ $actividad->subject_id }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($actividad->changes()->isNotEmpty())
                                        <details class="group text-left">
                                            <summary class="btn-ghost cursor-pointer list-none py-1.5 text-xs">Detalle</summary>
                                            <div class="absolute z-20 mt-2 w-[min(34rem,80vw)] rounded-xl border border-line bg-surface p-3 shadow-xl">
                                                <pre class="max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-surface-alt p-3 text-xs text-content-muted">{{ json_encode($actividad->changes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-xs text-content-subtle">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($actividades->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $actividades->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
