<x-guest-layout :title="'Verificación de correo'" :contexto="'app'">
    @if ($verificado)
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-100 dark:bg-accent-900 text-accent-800 dark:text-accent-300">
                <x-icon name="check" class="h-7 w-7" />
            </span>

            <h1 class="mt-4 font-display text-2xl font-semibold text-brand-900 dark:text-content">Correo verificado</h1>
            <p class="mt-2 text-sm text-content-muted">
                Confirmamos la dirección <span class="font-medium text-content-muted">{{ $usuario->email }}</span>.
                Vuelve a la aplicación de GAZ e inicia sesión para continuar.
            </p>
        </div>
    @else
        <div class="text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300">
                <x-icon name="alert" class="h-7 w-7" />
            </span>

            <h1 class="mt-4 font-display text-2xl font-semibold text-brand-900 dark:text-content">Este enlace ya no sirve</h1>
            <p class="mt-2 text-sm text-content-muted">
                El enlace de verificación venció, ya se usó o no corresponde a ninguna
                cuenta. Abre la aplicación de GAZ y pide que te reenviemos el correo.
            </p>
        </div>
    @endif

    <p class="mt-8 text-center text-xs text-content-subtle">
        Si tienes problemas para entrar, escríbenos desde la app.
    </p>
</x-guest-layout>
