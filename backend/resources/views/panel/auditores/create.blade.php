<x-app-layout>
    <x-slot name="title">Nuevo auditor</x-slot>
    <x-slot name="subtitle">La cuenta queda aprobada y verificada al crearla</x-slot>

    <x-slot name="actions">
        <a href="{{ route('panel.auditores.index') }}" class="btn-ghost">Volver</a>
    </x-slot>

    <form method="POST" action="{{ route('panel.auditores.store') }}" class="max-w-3xl space-y-5">
        @csrf

        <section class="card">
            <header class="border-b border-ink-100 px-5 py-4">
                <h2 class="panel-title">Datos del auditor</h2>
                <p class="text-xs text-ink-500">Podrá entrar al panel con este correo y contraseña.</p>
            </header>

            <div class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <label for="first_name" class="form-label">Nombre</label>
                    <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}"
                           class="form-input" required maxlength="80" autocomplete="given-name">
                    @error('first_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="last_name" class="form-label">Apellido</label>
                    <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}"
                           class="form-input" required maxlength="80" autocomplete="family-name">
                    @error('last_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="national_id" class="form-label">Cédula</label>
                    <input id="national_id" name="national_id" type="text" value="{{ old('national_id') }}"
                           class="form-input" required inputmode="numeric" pattern="[0-9]{6,9}" maxlength="9"
                           placeholder="14988776">
                    <p class="form-hint">Solo números, sin puntos.</p>
                    @error('national_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="form-label">Teléfono</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                           class="form-input" required maxlength="25" placeholder="0412-1234567"
                           autocomplete="tel">
                    @error('phone')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}"
                           class="form-input" required maxlength="255" autocomplete="email">
                    @error('email')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" name="password" type="password" class="form-input" required
                           minlength="12" autocomplete="new-password">
                    <p class="form-hint">Mínimo 12 caracteres, con letras y números.</p>
                    @error('password')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Repite la contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           class="form-input" required minlength="12" autocomplete="new-password">
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-ink-100 bg-white p-4 shadow-card">
            <p class="text-sm text-ink-500">
                Comparte la contraseña por un canal seguro. El auditor puede cambiarla y activar el doble factor al entrar.
            </p>
            <button type="submit" class="btn-primary">
                <x-icon name="check" class="h-4 w-4" />
                Crear auditor
            </button>
        </div>
    </form>
</x-app-layout>
