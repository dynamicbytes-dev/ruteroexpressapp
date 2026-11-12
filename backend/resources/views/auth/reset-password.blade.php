<x-guest-layout :title="'Nueva contraseña'" :contexto="'app'">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-brand-900 dark:text-content">Crea una contraseña nueva</h1>
        <p class="mt-1 text-sm text-content-muted">
            Al guardarla se cierran todas las sesiones abiertas de tu cuenta, incluida la del teléfono.
        </p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" name="email" type="email"
                          :value="old('email', $email)"
                          required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Contraseña nueva')" />
            <x-text-input id="password" name="password" type="password"
                          required autocomplete="new-password" />
            <p class="form-hint">Mínimo 8 caracteres, con al menos una letra y un número.</p>
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Repite la contraseña')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                          required autocomplete="new-password" />
            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button class="w-full">
            Guardar contraseña
        </x-primary-button>
    </form>
</x-guest-layout>
