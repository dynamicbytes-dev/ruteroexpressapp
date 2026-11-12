<x-app-layout>
    <x-slot name="title">Rendimiento de auditores</x-slot>
    <x-slot name="subtitle">Carga de trabajo de los auditores secundarios en el período elegido</x-slot>

    <x-slot name="actions">
        <a href="{{ route('panel.auditores.index') }}" class="btn-outline">
            <x-icon name="users" class="h-4 w-4" />
            Cuentas de auditor
        </a>
    </x-slot>

    <form method="GET" action="{{ route('panel.auditores.rendimiento') }}" class="card p-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="mr-2 text-sm font-medium text-content-muted">Período:</span>

            @foreach ([7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días'] as $valor => $etiqueta)
                <a href="{{ route('panel.auditores.rendimiento', ['dias' => $valor]) }}"
                   class="{{ $dias === $valor ? 'btn-primary' : 'btn-ghost' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach

            <span class="ml-auto text-xs text-content-muted">
                {{ $desde->translatedFormat('d M Y') }} — {{ $hasta->translatedFormat('d M Y') }}
            </span>
        </div>
    </form>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Auditores secundarios', 'valor' => number_format($resumen['auditores'], 0, ',', '.'), 'detalle' => 'Con acceso al panel', 'icono' => 'users'],
            ['label' => 'Decisiones', 'valor' => number_format($resumen['decisiones'], 0, ',', '.'), 'detalle' => 'Cuentas y documentos revisados', 'icono' => 'shield'],
            ['label' => 'Gestiones de soporte', 'valor' => number_format($resumen['soporte'], 0, ',', '.'), 'detalle' => 'Respuestas y conversaciones cerradas', 'icono' => 'chat'],
            ['label' => 'Operaciones financieras', 'valor' => number_format($resumen['finanzas'], 0, ',', '.'), 'detalle' => 'Recargas y retiros revisados', 'icono' => 'wallet'],
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

    <section class="mt-6 card">
        @if ($filas->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="chart" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">Todavía no hay auditores secundarios</p>
                <p class="mt-1 text-sm text-content-muted">
                    Crea una cuenta de auditor para empezar a medir su trabajo.
                </p>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
                <div>
                    <h2 class="panel-title">Ranking de gestión</h2>
                    <p class="text-xs text-content-muted">
                        Ordenado por cantidad total de gestiones. No incluye al auditor principal.
                    </p>
                </div>
                <span class="text-xs text-content-muted">
                    {{ number_format($filas->sum('gestiones'), 0, ',', '.') }} gestiones en total
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Auditor</th>
                            <th class="px-5 py-3 font-semibold">Gestiones</th>
                            <th class="px-5 py-3 font-semibold">Decisiones</th>
                            <th class="px-5 py-3 font-semibold">Soporte</th>
                            <th class="px-5 py-3 font-semibold">Finanzas</th>
                            <th class="px-5 py-3 font-semibold">Última actividad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($filas as $fila)
                            @php($auditor = $fila['auditor'])
                            <tr class="align-top hover:bg-surface-alt/60">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-content">{{ $auditor->name }}</div>
                                    <div class="text-xs text-content-muted">V-{{ $auditor->national_id }}</div>
                                    <div class="mt-1 text-xs text-content-muted">{{ $auditor->email }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-display text-lg font-semibold text-brand-900 dark:text-content">
                                        {{ number_format($fila['gestiones'], 0, ',', '.') }}
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        {{ number_format($fila['promedio_diario'], 1, ',', '.') }} por día
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-content">
                                        {{ number_format($fila['decisiones'], 0, ',', '.') }}
                                    </div>
                                    <div class="mt-1 text-xs text-content-muted">
                                        {{ $fila['aprobaciones'] }} aprob. · {{ $fila['rechazos'] }} rech.
                                    </div>
                                    @if ($fila['suspensiones'] > 0 || $fila['reactivaciones'] > 0)
                                        <div class="text-xs text-content-muted">
                                            {{ $fila['suspensiones'] }} susp. · {{ $fila['reactivaciones'] }} react.
                                        </div>
                                    @endif
                                    <div class="mt-1 text-xs font-medium text-brand-700 dark:text-accent-300">
                                        @if ($fila['tasa_aprobacion'] === null)
                                            Sin decisiones evaluables
                                        @else
                                            {{ $fila['tasa_aprobacion'] }}% de aprobación
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-content">
                                        {{ $fila['soporte_mensajes'] }} respuesta(s)
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        {{ $fila['soporte_cierres'] }} conversación(es) cerrada(s)
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-medium text-content">
                                        {{ $fila['recargas'] }} recarga(s)
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        {{ $fila['recargas_aprobadas'] }} aprobada(s)
                                    </div>
                                    <div class="mt-1 font-medium text-content">
                                        {{ $fila['retiros'] }} retiro(s)
                                    </div>
                                    <div class="text-xs text-content-muted">
                                        {{ $fila['retiros_pagados'] }} pagado(s)
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-content-muted">
                                    @if ($fila['ultima_actividad'] === null)
                                        <span class="badge-pending">Sin actividad</span>
                                    @else
                                        <div class="text-content-muted">
                                            {{ $fila['ultima_actividad']->translatedFormat('d M Y') }}
                                        </div>
                                        <div class="text-xs text-content-muted">
                                            {{ $fila['ultima_actividad']->format('H:i') }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="mt-4 text-xs text-content-muted">
        Las métricas se calculan con las acciones registradas en el período. Una misma gestión puede incluir
        varias operaciones (por ejemplo, la aprobación de cada documento de una cuenta).
    </p>
</x-app-layout>
