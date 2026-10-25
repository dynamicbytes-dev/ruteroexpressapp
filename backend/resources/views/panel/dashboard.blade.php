<x-app-layout>
    <x-slot name="title">Inicio</x-slot>
    <x-slot name="subtitle">Lo que está pendiente de tu revisión hoy</x-slot>

    {{-- ------------------------------------------------------------------
         Colas de trabajo
         ------------------------------------------------------------------ --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($queues as $queue)
            @php
                $pendiente = $queue['count'] > 0;
            @endphp

            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-medium text-ink-600">{{ $queue['label'] }}</p>

                    @if ($pendiente)
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500" title="Hay trabajo pendiente"></span>
                    @endif
                </div>

                <p class="mt-3 font-display text-4xl font-semibold {{ $pendiente ? 'text-brand-900' : 'text-ink-300' }}">
                    {{ $queue['count'] }}
                </p>

                <p class="mt-1 text-xs text-ink-500">{{ $queue['help'] }}</p>

                @if ($queue['url'])
                    <a href="{{ $queue['url'] }}" class="btn-outline mt-4 w-full">Revisar</a>
                @else
                    <span class="mt-4 block rounded-xl bg-ink-50 px-3 py-2 text-center text-xs font-medium text-ink-400">
                        Próximamente
                    </span>
                @endif
            </div>
        @endforeach
    </section>

    {{-- ------------------------------------------------------------------
         Resumen
         ------------------------------------------------------------------ --}}
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Conductores aprobados', 'valor' => $summary['conductores_activos'], 'icono' => 'moto'],
            ['label' => 'Pasajeros registrados', 'valor' => $summary['pasajeros'], 'icono' => 'users'],
            ['label' => 'Cuentas aprobadas', 'valor' => $summary['aprobados'], 'icono' => 'shield'],
            ['label' => 'Documentos rechazados', 'valor' => $summary['rechazados'], 'icono' => 'document'],
        ] as $stat)
            <div class="card flex items-center gap-4 p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <x-icon :name="$stat['icono']" class="h-5 w-5" />
                </span>
                <div>
                    <p class="font-display text-2xl font-semibold text-brand-900">{{ $stat['valor'] }}</p>
                    <p class="text-xs text-ink-500">{{ $stat['label'] }}</p>
                </div>
            </div>
        @endforeach
    </section>

    {{-- ------------------------------------------------------------------
         Documentos de conductores que necesitan atención
         ------------------------------------------------------------------ --}}
    @if ($documentosPorVencer->isNotEmpty() || $documentosVencidos->isNotEmpty())
        <section class="card mt-6">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4">
                <div>
                    <h2 class="panel-title">Documentos de conductores</h2>
                    <p class="text-xs text-ink-500">Vencimientos de los próximos 30 días y documentos ya vencidos</p>
                </div>
                <div class="flex gap-2 text-xs">
                    <span class="badge-pending">{{ $documentosPorVencer->count() }} por vencer</span>
                    <span class="badge-rejected">{{ $documentosVencidos->count() }} vencidos</span>
                </div>
            </header>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Conductor</th>
                            <th class="px-5 py-3 font-semibold">Documento</th>
                            <th class="px-5 py-3 font-semibold">Vence</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($documentosVencidos as $documento)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $documento->user->name }}</div>
                                    <div class="text-xs text-ink-500">V-{{ $documento->user->national_id }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $documento->type->label() }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $documento->expires_at->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3"><span class="badge-rejected">Vencido</span></td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.usuarios.show', $documento->user) }}" class="btn-outline">Revisar</a>
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($documentosPorVencer as $documento)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $documento->user->name }}</div>
                                    <div class="text-xs text-ink-500">V-{{ $documento->user->national_id }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $documento->type->label() }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $documento->expires_at->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="badge-pending">
                                        {{ $documento->daysUntilExpiration() }} día(s)
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.usuarios.show', $documento->user) }}" class="btn-outline">Revisar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- ------------------------------------------------------------------
         Últimos registros
         ------------------------------------------------------------------ --}}
    <section class="card mt-6">
        <header class="flex items-center justify-between gap-4 border-b border-ink-100 px-5 py-4">
            <div>
                <h2 class="panel-title">Últimos registros</h2>
                <p class="text-xs text-ink-500">Pasajeros y conductores que enviaron sus documentos</p>
            </div>
        </header>

        @if ($recent->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-ink-500">
                Todavía no hay registros por revisar.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Persona</th>
                            <th class="px-5 py-3 font-semibold">Rol</th>
                            <th class="px-5 py-3 font-semibold">Documentos</th>
                            <th class="px-5 py-3 font-semibold">Enviado</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($recent as $usuario)
                            @php
                                $requeridos = $usuario->role->requiredDocuments();
                                $subidos = $usuario->documents->count();
                                $badges = [
                                    'pending' => 'badge-pending',
                                    'approved' => 'badge-approved',
                                    'rejected' => 'badge-rejected',
                                    'suspended' => 'badge-rejected',
                                    'blocked' => 'badge-neutral',
                                ];
                            @endphp
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $usuario->name }}</div>
                                    <div class="text-xs text-ink-500">V-{{ $usuario->national_id }} · {{ $usuario->email }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $usuario->role->label() }}</td>
                                <td class="px-5 py-3">
                                    <span class="text-ink-700">{{ $subidos }} de {{ count($requeridos) }}</span>
                                    @if ($subidos < count($requeridos))
                                        <span class="badge-neutral ml-2">Incompleto</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-500">
                                    {{ $usuario->submitted_at?->translatedFormat('d M, H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $badges[$usuario->status->value] ?? 'badge-neutral' }}">
                                        {{ $usuario->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
