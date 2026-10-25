<x-app-layout>
    <x-slot name="title">Tasa de cambio</x-slot>
    <x-slot name="subtitle">Bolívares por dólar · se aplica a recargas, cotizaciones y conversiones</x-slot>

    <div class="grid gap-5 xl:grid-cols-3">
        {{-- ------------------------------------------------------------
             Tasa vigente en GAZ
             ------------------------------------------------------------ --}}
        <section class="card overflow-hidden xl:col-span-1 flex flex-col justify-between">
            <div class="bg-brand-900 px-6 py-6 text-white">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wider text-accent-400">Tasa vigente en GAZ</p>
                    <span class="inline-flex items-center rounded-full bg-accent-400/20 px-2.5 py-0.5 text-xs font-medium text-accent-300">
                        Activa
                    </span>
                </div>

                @if ($vigente)
                    <p class="mt-3 font-display text-4xl font-semibold">
                        {{ number_format((float) $vigente->rate, 4, ',', '.') }}
                        <span class="text-lg font-medium text-brand-100/70">Bs / $</span>
                    </p>
                    <p class="mt-2 text-sm text-brand-100/80">
                        Aplica desde el {{ $vigente->effective_date->translatedFormat('d \d\e F \d\e Y') }}.
                    </p>
                    <p class="mt-1 text-xs text-brand-100/60">
                        Cargada por {{ $vigente->author?->name ?? 'Sincronización BCV / Auditor' }}
                        el {{ $vigente->created_at?->translatedFormat('d M Y, H:i') }}.
                    </p>
                @else
                    <p class="mt-3 font-display text-2xl font-semibold text-amber-300">Sin tasa cargada</p>
                    <p class="mt-2 text-sm text-brand-100/80">
                        Carga la tasa del día o sincroniza con el BCV para activar recargas.
                    </p>
                @endif
            </div>

            <div class="p-5 border-t border-ink-100 bg-white">
                <p class="text-xs text-ink-500">
                    Las recargas y pagos en bolívares se calculan con esta tasa oficial vigente.
                    Cada cambio queda registrado en el historial de auditoría.
                </p>
            </div>
        </section>

        {{-- ------------------------------------------------------------
             Banco Central de Venezuela (API BCV Oficial)
             ------------------------------------------------------------ --}}
        <section class="card overflow-hidden xl:col-span-1 flex flex-col justify-between border-2 border-accent-400/30">
            <div class="bg-gradient-to-br from-brand-950 via-brand-900 to-accent-950 px-6 py-6 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2.5 w-2.5 rounded-full bg-accent-400 animate-pulse"></span>
                        <p class="text-xs font-semibold uppercase tracking-wider text-accent-300">API Banco Central (BCV)</p>
                    </div>
                    <span class="text-[11px] font-semibold text-accent-300/80 uppercase tracking-wider">Oficial</span>
                </div>

                @if (!empty($bcv) && $bcv['success'] && !empty($bcv['rate']))
                    <p class="mt-3 font-display text-4xl font-semibold text-white">
                        {{ number_format((float) $bcv['rate'], 4, ',', '.') }}
                        <span class="text-lg font-medium text-accent-400">Bs / $</span>
                    </p>
                    <p class="mt-2 text-sm text-brand-100/90">
                        Fecha oficial: {{ \Carbon\Carbon::parse($bcv['effective_date'])->translatedFormat('d \d\e F \d\e Y') }}
                    </p>
                    <p class="mt-1 text-xs text-brand-100/60 truncate" title="{{ $bcv['source'] }}">
                        Fuente: {{ $bcv['source'] }}
                    </p>
                @else
                    <p class="mt-3 font-display text-xl font-semibold text-amber-300">Consultando BCV...</p>
                    <p class="mt-2 text-xs text-brand-100/70">
                        {{ $bcv['message'] ?? 'Puedes sincronizar o reintentar la conexión oficial con el BCV.' }}
                    </p>
                @endif
            </div>

            <div class="p-5 border-t border-ink-100 bg-white space-y-3">
                <form method="POST" action="{{ route('panel.tasa.bcv') }}">
                    @csrf
                    <button type="submit" class="btn-primary w-full justify-center">
                        <x-icon name="refresh" class="h-4 w-4" />
                        Sincronizar tasa oficial BCV
                    </button>
                </form>

                @if (!empty($bcv) && $bcv['success'] && !empty($bcv['rate']))
                    <button type="button"
                            onclick="document.getElementById('rate').value = '{{ $bcv['rate'] }}'; document.getElementById('effective_date').value = '{{ $bcv['effective_date'] }}';"
                            class="btn-ghost w-full justify-center text-xs text-brand-800">
                        Copiar valores al formulario manual
                    </button>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------
             Cargar o modificar tasa manualmente
             ------------------------------------------------------------ --}}
        <section class="card p-5 xl:col-span-1">
            <h2 class="panel-title">Carga manual</h2>
            <p class="mt-1 text-sm text-ink-500">
                Puedes fijar una tasa personalizada si lo requieres operativamente.
            </p>

            <form method="POST" action="{{ route('panel.tasa.store') }}" class="mt-4 space-y-4">
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
                           class="form-input" placeholder="Ej.: 886.2271">
                    @error('rate')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-secondary w-full justify-center">
                    <x-icon name="check" class="h-4 w-4" />
                    Guardar tasa manual
                </button>
            </form>
        </section>
    </div>

    {{-- ------------------------------------------------------------
         Histórico de tasas registradas
         ------------------------------------------------------------ --}}
    <section class="card mt-6">
        <header class="border-b border-ink-100 px-5 py-4">
            <h2 class="panel-title">Historial de tasas registradas</h2>
            <p class="text-xs text-ink-500">Registro histórico inmutable para respaldo de recargas y pagos en bolívares.</p>
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
                            <th class="px-5 py-3 font-semibold">Registrada por</th>
                            <th class="px-5 py-3 font-semibold">Fecha de registro</th>
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
                                <td class="px-5 py-3 text-ink-600">
                                    {{ $tasa->author?->name ?? 'API BCV / Sistema' }}
                                </td>
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
