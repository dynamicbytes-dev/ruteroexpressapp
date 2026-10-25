<x-guest-layout :title="'Recuperar contraseña'" :contexto="'app'">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-brand-900">Recuperar contraseña</h1>
        <p class="mt-1 text-sm text-ink-500">
            Escribe el correo con el que te registraste y te enviamos un enlace para crear
            una contraseña nueva.
        </p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" name="email" type="email"
                          :value="old('email')"
                          required autofocus autocomplete="username"
                          placeholder="tucorreo@gmail.com" />
            <p class="form-hint">Solo se aceptan cuentas de Gmail.</p>
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <x-primary-button class="w-full">
            Enviarme el enlace
        </x-primary-button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-500">
        <a href="{{ route('login') }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-4 hover:text-brand-900">
            Volver al inicio de sesión
        </a>
    </p>
</x-guest-layout>
