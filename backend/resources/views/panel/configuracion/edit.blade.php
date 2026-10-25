<x-app-layout>
    <x-slot name="title">Configuración</x-slot>
    <x-slot name="subtitle">Tarifas por ciudad, asignación y billetera · los cambios aplican a las próximas operaciones</x-slot>

    @if ($ciudades->isNotEmpty())
        <form method="GET" action="{{ route('panel.configuracion.edit') }}" class="card mb-5 flex flex-wrap items-end gap-4 p-5">
            <div class="min-w-72 flex-1">
                <label for="ciudad" class="form-label">Ciudad para las tarifas</label>
                <select id="ciudad" name="ciudad" class="form-input pr-8" onchange="this.form.submit()">
                    @foreach ($ciudades as $opcion)
                        <option value="{{ $opcion->id }}" @selected($ciudad?->id === $opcion->id)>
                            {{ $opcion->displayName() }}
                        </option>
                    @endforeach
                </select>
                <p class="form-hint">Cada zona tiene sus propios precios. Los viajes ya cerrados conservan la tarifa con la que se cobraron.</p>
            </div>

            <noscript>
                <button type="submit" class="btn-outline">Cambiar ciudad</button>
            </noscript>
        </form>
    @endif

    <form method="POST" action="{{ route('panel.configuracion.update') }}" class="space-y-5">
        @csrf
        @method('PUT')
        <input type="hidden" name="city_id" value="{{ $ciudad?->id }}">

        @foreach ($grupos as $clave => $grupo)
            <section class="card">
                <header class="border-b border-ink-100 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="panel-title">{{ $grupo['label'] }}</h2>
                        @if ($clave === 'tarifas' && $ciudad)
                            <span class="badge-approved">{{ $ciudad->displayName() }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-ink-500">{{ $grupo['help'] }}</p>
                </header>

                <div class="grid gap-5 p-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($grupo['campos'] as $campo)
                        @php
                            $esTexto = $campo['key'] === 'banco_instrucciones';
                            $esHora = in_array($campo['key'], ['nocturno_desde', 'nocturno_hasta'], true);
                            $esJson = $campo['type'] === 'json';
                            $tipo = $esHora ? 'time' : ($esTexto || $esJson || $campo['type'] === 'string' ? 'text' : 'number');
                            $paso = $campo['type'] === 'decimal' ? '0.01' : '1';
                        @endphp

                        <div class="{{ $esTexto ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                            <label for="{{ $campo['key'] }}" class="form-label">{{ $campo['label'] }}</label>

                            @if ($esTexto)
                                <textarea id="{{ $campo['key'] }}" name="{{ $campo['key'] }}" rows="3"
                                          class="form-input">{{ old($campo['key'], $campo['value']) }}</textarea>
                            @else
                                <input id="{{ $campo['key'] }}" name="{{ $campo['key'] }}"
                                       type="{{ $tipo }}"
                                       @if ($tipo === 'number') step="{{ $paso }}" @endif
                                       value="{{ old($campo['key'], $campo['value']) }}"
                                       class="form-input">
                            @endif

                            @if ($campo['description'])
                                <p class="form-hint">{{ $campo['description'] }}</p>
                            @endif

                            @error($campo['key'])
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink-100 bg-white p-4 shadow-card">
            <p class="text-sm text-ink-500">
                Los viajes ya cerrados conservan las tarifas con las que se cobraron.
            </p>

            <button type="submit" class="btn-primary">
                <x-icon name="check" class="h-4 w-4" />
                Guardar configuración
            </button>
        </div>
    </form>
</x-app-layout>
