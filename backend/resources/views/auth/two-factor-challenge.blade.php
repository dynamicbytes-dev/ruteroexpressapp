<x-guest-layout :title="__('Verificación en dos pasos')">
    <div class="mb-6">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-accent-300">
            <x-icon name="shield" class="h-6 w-6" />
        </span>
        <h1 class="mt-3 font-display text-2xl font-semibold text-brand-900 dark:text-content">Verificación en dos pasos</h1>
        <p class="mt-1 text-sm text-content-muted">
            Abre tu aplicación de autenticación y escribe el código de 6 dígitos para
            <span class="font-medium text-content-muted">{{ $email }}</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('panel.2fa.verify') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="code" value="Código de verificación" />
            <x-text-input id="code" name="code" type="text" inputmode="numeric"
                          autocomplete="one-time-code" autofocus required
                          placeholder="123 456" />
            <p class="form-hint">
                También puedes usar uno de los códigos de recuperación que guardaste al activar el doble factor.
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('code')" />
        </div>

        <x-primary-button class="w-full">Verificar y entrar</x-primary-button>

        <p class="text-center text-sm text-content-muted">
            <a href="{{ route('login') }}" class="font-medium text-brand-700 dark:text-accent-300 underline decoration-brand-300 underline-offset-4 hover:text-brand-900 dark:hover:text-content">
                Volver al inicio de sesión
            </a>
        </p>
    </form>
</x-guest-layout>
