<x-guest-layout :title="'Confirmar contraseña'">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-semibold text-brand-900">Confirma tu contraseña</h1>
        <p class="mt-1 text-sm text-ink-500">
            Esta es un área protegida del panel. Escribe tu contraseña para continuar.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Contraseña')" />
            <x-text-input id="password" name="password" type="password"
                          required autocomplete="current-password" />
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">
            Confirmar
        </x-primary-button>
    </form>
</x-guest-layout>
