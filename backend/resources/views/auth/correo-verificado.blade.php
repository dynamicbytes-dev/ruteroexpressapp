<x-guest-layout :title="'Verificación de correo'" :contexto="'app'">
    @if ($verificado)
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-100 text-accent-800">
                <x-icon name="check" class="h-7 w-7" />
            </span>

            <h1 class="mt-4 font-display text-2xl font-semibold text-brand-900">Correo verificado</h1>
            <p class="mt-2 text-sm text-ink-500">
                Confirmamos la dirección <span class="font-medium text-ink-700">{{ $usuario->email }}</span>.
                Vuelve a la aplicación de GAZ e inicia sesión para continuar.
            </p>
        </div>
    @else
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-800">
                <x-icon name="alert" class="h-7 w-7" />
            </span>

            <h1 class="mt-4 font-display text-2xl font-semibold text-brand-900">Este enlace ya no sirve</h1>
            <p class="mt-2 text-sm text-ink-500">
                El enlace de verificación venció, ya se usó o no corresponde a ninguna
                cuenta. Abre la aplicación de GAZ y pide que te reenviemos el correo.
            </p>
        </div>
    @endif

    <p class="mt-8 text-center text-xs text-ink-400">
        Si tienes problemas para entrar, escríbenos desde la app.
    </p>
</x-guest-layout>
