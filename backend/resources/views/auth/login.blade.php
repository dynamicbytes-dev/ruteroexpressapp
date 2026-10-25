<x-guest-layout :title="__('Iniciar sesión')">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-brand-900">Iniciar sesión</h1>
        <p class="mt-1 text-sm text-ink-500">Ingresa con tu cuenta de auditor.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" name="email" type="email"
                          :value="old('email')"
                          required autofocus autocomplete="username"
                          placeholder="auditor@motoruta.app" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-text-input id="password" name="password" type="password"
                          required autocomplete="current-password" />
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox" name="remember"
                       class="rounded border-ink-300 text-brand-700 shadow-sm focus:ring-brand-500">
                <span class="text-sm text-ink-600">Mantener sesión</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm font-medium text-brand-700 underline decoration-brand-300 underline-offset-4 hover:text-brand-900">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">
            Entrar al panel
        </x-primary-button>
    </form>
</x-guest-layout>
