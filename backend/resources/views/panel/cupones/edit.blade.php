<x-app-layout>
    <x-slot name="title">Editar cupón · {{ $cupo->code }}</x-slot>
    <x-slot name="subtitle">Modifica los datos del cupón. Los viajes ya creados no cambian.</x-slot>

    <section class="card p-5 max-w-2xl">
        <form method="POST" action="{{ route('panel.cupones.update', $cupo) }}" class="grid gap-5 sm:grid-cols-2">
            @csrf
            @method('PUT')

            <div>
                <label for="code" class="form-label">Código del cupón</label>
                <input id="code" name="code" type="text"
                       value="{{ old('code', $cupo->code) }}"
                       class="form-input" maxlength="32">
                @error('code')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="discount_percent" class="form-label">Descuento porcentual</label>
                <input id="discount_percent" name="discount_percent" type="number" step="0.01" min="0" max="100"
                       value="{{ old('discount_percent', $cupo->discount_percent) }}"
                       class="form-input">
                @error('discount_percent')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="min_purchase" class="form-label">Compra mínima (USD)</label>
                <input id="min_purchase" name="min_purchase" type="number" step="0.01" min="0"
                       value="{{ old('min_purchase', $cupo->min_purchase) }}"
                       class="form-input">
                @error('min_purchase')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_discount" class="form-label">Descuento máximo (USD)</label>
                <input id="max_discount" name="max_discount" type="number" step="0.01" min="0"
                       value="{{ old('max_discount', $cupo->max_discount) }}"
                       class="form-input">
                @error('max_discount')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="valid_from" class="form-label">Vigente desde</label>
                <input id="valid_from" name="valid_from" type="date"
                       value="{{ old('valid_from', $cupo->valid_from->toDateString()) }}"
                       class="form-input">
                @error('valid_from')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="valid_until" class="form-label">Vigente hasta</label>
                <input id="valid_until" name="valid_until" type="date"
                       value="{{ old('valid_until', $cupo->valid_until?->toDateString()) }}"
                       class="form-input">
                @error('valid_until')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_uses_global" class="form-label">Límite de usos totales</label>
                <input id="max_uses_global" name="max_uses_global" type="number" min="1"
                       value="{{ old('max_uses_global', $cupo->max_uses_global) }}"
                       class="form-input">
                @error('max_uses_global')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="max_uses_per_user" class="form-label">Límite de usos por usuario</label>
                <input id="max_uses_per_user" name="max_uses_per_user" type="number" min="1"
                       value="{{ old('max_uses_per_user', $cupo->max_uses_per_user) }}"
                       class="form-input">
                @error('max_uses_per_user')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="city_id" class="form-label">Ciudad (opcional)</label>
                <select id="city_id" name="city_id" class="form-input">
                    <option value="">Todas las ciudades</option>
                    @foreach ($ciudades as $ciudad)
                        <option value="{{ $ciudad->id }}" {{ old('city_id', $cupo->city_id) == $ciudad->id ? 'selected' : '' }}>
                            {{ $ciudad->name }}
                        </option>
                    @endforeach
                </select>
                @error('city_id')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="form-label flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1"
                           {{ old('is_active', $cupo->is_active) ? 'checked' : '' }}
                           class="form-checkbox">
                    Activo
                </label>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary">
                    <x-icon name="check" class="h-4 w-4" />
                    Guardar cambios
                </button>
                <a href="{{ route('panel.cupones.show', $cupo) }}" class="btn-outline ml-3">Cancelar</a>
            </div>
        </form>
    </section>
</x-app-layout>
