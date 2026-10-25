<x-app-layout>
    <x-slot name="title">Nuevo cupón</x-slot>
    <x-slot name="subtitle">Descuento porcentual para pasajeros</x-slot>

    <section class="card p-5 max-w-2xl">
        <form method="POST" action="{{ route('panel.cupones.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf

            <div>
                <label for="code" class="form-label">Código del cupón</label>
                <input id="code" name="code" type="text"
                       value="{{ old('code') }}"
                       placeholder="Ej: BIENVENIDO50"
                       class="form-input" maxlength="32">
                @error('code')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-400 mt-1">Mayúsculas, sin espacios. Ej: DESCUENTO50</p>
            </div>

            <div class="sm:col-span-2">
                <label for="discount_percent" class="form-label">Descuento porcentual</label>
                <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100"
                       value="{{ old('discount_percent', 10) }}"
                       class="form-input">
                @error('discount_percent')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-400 mt-1">0 = sin descuento, 100 = viaje gratis</p>
            </div>

            <div>
                <label for="min_purchase" class="form-label">Compra mínima (USD)</label>
                <input id="min_purchase" name="min_purchase" type="number" step="0.01" min="0"
                       value="{{ old('min_purchase') }}"
                       class="form-input" placeholder="Opcional">
                @error('min_purchase')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_discount" class="form-label">Descuento máximo (USD)</label>
                <input id="max_discount" name="max_discount" type="number" step="0.01" min="0"
                       value="{{ old('max_discount') }}"
                       class="form-input" placeholder="Opcional">
                @error('max_discount')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-400 mt-1">Tope al monto del descuento</p>
            </div>

            <div class="sm:col-span-2">
                <label for="valid_from" class="form-label">Vigente desde</label>
                <input id="valid_from" name="valid_from" type="date"
                       value="{{ old('valid_from', today()->toDateString()) }}"
                       class="form-input">
                @error('valid_from')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="valid_until" class="form-label">Vigente hasta</label>
                <input id="valid_until" name="valid_until" type="date"
                       value="{{ old('valid_until') }}"
                       class="form-input">
                @error('valid_until')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-400 mt-1">Dejar vacío para que no expire.</p>
            </div>

            <div>
                <label for="max_uses_global" class="form-label">Límite de usos totales</label>
                <input id="max_uses_global" name="max_uses_global" type="number" min="1"
                       value="{{ old('max_uses_global') }}"
                       class="form-input" placeholder="Opcional">
                @error('max_uses_global')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_uses_per_user" class="form-label">Límite de usos por usuario</label>
                <input id="max_uses_per_user" name="max_uses_per_user" type="number" min="1"
                       value="{{ old('max_uses_per_user') }}"
                       class="form-input" placeholder="Opcional">
                @error('max_uses_per_user')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="city_id" class="form-label">Ciudad (opcional)</label>
                <select id="city_id" name="city_id" class="form-input">
                    <option value="">Todas las ciudades</option>
                    @foreach ($ciudades as $ciudad)
                        <option value="{{ $ciudad->id }}" {{ old('city_id') == $ciudad->id ? 'selected' : '' }}>
                            {{ $ciudad->name }}
                        </option>
                    @endforeach
                </select>
                @error('city_id')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <p class="text-xs text-ink-400 mt-1">Si se elige una ciudad, solo aplica a viajes de esa zona.</p>
            </div>

            <div class="sm:col-span-2">
                <label class="form-label flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1"
                           {{ old('is_active', true) ? 'checked' : '' }}
                           class="form-checkbox">
                    Activo
                </label>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary">
                    <x-icon name="check" class="h-4 w-4" />
                    Crear cupón
                </button>
                <a href="{{ route('panel.cupones.index') }}" class="btn-outline ml-3">Cancelar</a>
            </div>
        </form>
    </section>
</x-app-layout>
