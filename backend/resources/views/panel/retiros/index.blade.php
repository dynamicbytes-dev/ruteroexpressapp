<x-app-layout>
    <x-slot name="title">Retiros</x-slot>
    <x-slot name="subtitle">Pagos pendientes a conductores</x-slot>

    @php
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
        ];
    @endphp

    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? null;
                $pendiente = $clave === 'pending' && ($total ?? 0) > 0;
            @endphp

            <a href="{{ route('panel.retiros.index', array_filter(['tab' => $clave, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white' : 'text-ink-600 hover:bg-ink-100 hover:text-brand-800' }}">
                {{ $etiqueta }}

                @if ($total !== null)
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                 {{ $activa ? 'bg-white/20 text-white' : ($pendiente ? 'bg-amber-100 text-amber-800' : 'bg-ink-100 text-ink-500') }}">
                        {{ $total }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('panel.retiros.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Nombre, cédula o correo del conductor">
            </div>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '')
            <a href="{{ route('panel.retiros.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar</a>
        @endif
    </form>

    <section class="card mt-4">
        @if ($retiros->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400">
                    <x-icon name="banknote" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900">No hay retiros en esta lista</p>
                <p class="mt-1 text-sm text-ink-500">
                    @if ($busqueda !== '')
                        Prueba con otra búsqueda o limpia el filtro.
                    @else
                        Cuando un conductor solicite un retiro aparecerá aquí.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Conductor</th>
                            <th class="px-5 py-3 font-semibold">Monto</th>
                            <th class="px-5 py-3 font-semibold">Cuenta destino</th>
                            <th class="px-5 py-3 font-semibold">Solicitado</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($retiros as $retiro)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $retiro->user->name }}</div>
                                    <div class="text-xs text-ink-500">V-{{ $retiro->user->national_id }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-display text-base font-semibold text-brand-900">
                                        ${{ number_format((float) $retiro->amount_usd, 2, '.', ',') }}
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-ink-800">{{ $retiro->bank }}</div>
                                    <div class="text-xs text-ink-500">{{ $retiro->account_number }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-500">
                                    {{ $retiro->created_at?->translatedFormat('d M, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $tintes[$retiro->status->tone()] ?? 'badge-neutral' }}">
                                        {{ $retiro->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.retiros.show', $retiro) }}" class="btn-outline">Revisar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($retiros->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">
                    {{ $retiros->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
