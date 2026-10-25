<x-app-layout>
    <x-slot name="title">Tasa de cambio</x-slot>
    <x-slot name="subtitle">Bolívares por dólar · se aplica a recargas y conversiones</x-slot>

    <div class="grid gap-5 xl:grid-cols-3">
        {{-- ------------------------------------------------------------
             Tasa vigente
             ------------------------------------------------------------ --}}
        <section class="card overflow-hidden xl:col-span-1">
            <div class="bg-brand-900 px-6 py-6 text-white">
                <p class="text-xs font-semibold uppercase tracking-wider text-accent-400">Tasa vigente</p>

                @if ($vigente)
                    <p class="mt-3 font-display text-4xl font-semibold">
                        {{ number_format((float) $vigente->rate, 4, ',', '.') }}
                        <span class="text-lg font-medium text-brand-100/70">Bs / $</span>
                    </p>
                    <p class="mt-2 text-sm text-brand-100/80">
                        Aplica desde el {{ $vigente->effective_date->translatedFormat('d \d\e F \d\e Y') }}.
                    </p>
                    <p class="mt-1 text-xs text-brand-100/60">
                        Cargada por {{ $vigente->author?->name ?? 'el equipo' }}
                        el {{ $vigente->created_at?->translatedFormat('d M Y, H:i') }}.
                    </p>
                @else
                    <p class="mt-3 font-display text-2xl font-semibold text-amber-300">Sin tasa cargada</p>
                    <p class="mt-2 text-sm text-brand-100/80">
                        Carga la tasa del día para poder aprobar recargas.
                    </p>
                @endif
            </div>

            <div class="p-5">
                <p class="text-xs text-ink-500">
                    El sistema no consulta ninguna fuente automática: la tasa la decide el auditor.
                    Cada cambio queda en el histórico con autor y fecha.
                </p>
            </div>
        </section>

        {{-- ------------------------------------------------------------
             Cargar tasa
             ------------------------------------------------------------ --}}
        <section class="card p-5 xl:col-span-2">
            <h2 class="panel-title">Cargar una tasa</h2>
            <p class="mt-1 text-sm text-ink-500">
                Si ya existe una tasa para esa fecha, se agrega al histórico y pasa a ser la vigente.
            </p>

            <form method="POST" action="{{ route('panel.tasa.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf

                <div>
                    <label for="effective_date" class="form-label">Vigente desde</label>
                    <input id="effective_date" name="effective_date" type="date"
                           max="{{ today()->toDateString() }}"
                           value="{{ old('effective_date', today()->toDateString()) }}"
                           class="form-input">
                    @error('effective_date')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="rate" class="form-label">Tasa (Bs por dólar)</label>
                    <input id="rate" name="rate" type="number" step="0.0001" min="0.0001"
                           value="{{ old('rate', $vigente?->rate) }}"
                           class="form-input" placeholder="Ej.: 40.0000">
                    @error('rate')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" />
                        Guardar tasa
                    </button>
                </div>
            </form>
        </section>
    </div>

    {{-- ------------------------------------------------------------
         Histórico
         ------------------------------------------------------------ --}}
    <section class="card mt-5">
        <header class="border-b border-ink-100 px-5 py-4">
            <h2 class="panel-title">Histórico</h2>
            <p class="text-xs text-ink-500">Ninguna tasa se borra: cada carga quedó registrada.</p>
        </header>

        @if ($historial->isEmpty())
            <p class="px-5 py-10 text-center text-sm text-ink-500">
                Todavía no se ha cargado ninguna tasa.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Vigente desde</th>
                            <th class="px-5 py-3 font-semibold">Tasa</th>
                            <th class="px-5 py-3 font-semibold">Cargada por</th>
                            <th class="px-5 py-3 font-semibold">Registrada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($historial as $tasa)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3 font-medium text-ink-900">
                                    {{ $tasa->effective_date->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-display text-base font-semibold text-brand-900">
                                        {{ number_format((float) $tasa->rate, 4, ',', '.') }}
                                    </span>
                                    <span class="text-xs text-ink-500">Bs/$</span>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $tasa->author?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-ink-500">
                                    {{ $tasa->created_at?->translatedFormat('d M Y, H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($historial->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">
                    {{ $historial->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
