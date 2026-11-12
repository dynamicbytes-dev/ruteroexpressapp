<x-app-layout>
    <x-slot name="title">Editar pasajero: {{ $pasajero->name }}</x-slot>
    <x-slot name="subtitle">Actualización de datos personales y estado de la cuenta</x-slot>

    <div class="max-w-3xl">
        <div class="mb-4">
            <a href="{{ route('panel.pasajeros.show', $pasajero) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 dark:text-accent-300 hover:underline">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Volver a la ficha del pasajero
            </a>
        </div>

        <section class="card p-6">
            <form method="POST" action="{{ route('panel.pasajeros.update', $pasajero) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="form-label">Nombre(s) *</label>
                        <input id="first_name" name="first_name" type="text" required maxlength="60"
                               value="{{ old('first_name', $pasajero->first_name) }}"
                               class="form-input">
                        @error('first_name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="last_name" class="form-label">Apellido(s) *</label>
                        <input id="last_name" name="last_name" type="text" required maxlength="60"
                               value="{{ old('last_name', $pasajero->last_name) }}"
                               class="form-input">
                        @error('last_name')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="national_id" class="form-label">Cédula de identidad *</label>
                        <input id="national_id" name="national_id" type="text" required maxlength="20"
                               value="{{ old('national_id', $pasajero->national_id) }}"
                               class="form-input font-mono">
                        @error('national_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="form-label">Teléfono celular</label>
                        <input id="phone" name="phone" type="text" maxlength="25"
                               value="{{ old('phone', $pasajero->phone) }}"
                               class="form-input font-mono" placeholder="Ej.: 04121234567">
                        @error('phone')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="email" class="form-label">Correo electrónico *</label>
                        <input id="email" name="email" type="email" required maxlength="255"
                               value="{{ old('email', $pasajero->email) }}"
                               class="form-input">
                        @error('email')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="city_id" class="form-label">Ciudad / Zona operativa</label>
                        <select id="city_id" name="city_id" class="form-input">
                            <option value="">Sin asignar</option>
                            @foreach ($ciudades as $ciudad)
                                <option value="{{ $ciudad->id }}" @selected((int) old('city_id', $pasajero->city_id) === (int) $ciudad->id)>
                                    {{ $ciudad->displayName() }}
                                </option>
                            @endforeach
                        </select>
                        @error('city_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="form-label">Estado de la cuenta *</label>
                        <select id="status" name="status" class="form-input" required>
                            @foreach ($estados as $estado)
                                <option value="{{ $estado->value }}" @selected(old('status', $pasajero->status->value) === $estado->value)>
                                    {{ $estado->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="address" class="form-label">Dirección habitual</label>
                        <textarea id="address" name="address" rows="2" maxlength="255"
                                  class="form-input">{{ old('address', $pasajero->address) }}</textarea>
                        @error('address')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-line pt-5">
                    <a href="{{ route('panel.pasajeros.show', $pasajero) }}" class="btn-ghost">
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary">
                        <x-icon name="check" class="h-4 w-4" />
                        Guardar cambios
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
