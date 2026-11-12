<section>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-xl">
            <h2 class="panel-title">Verificación en dos pasos</h2>
            <p class="mt-1 text-sm text-content-muted">
                Un código adicional de tu aplicación de autenticación protege el panel aunque
                alguien conozca tu contraseña.
            </p>
        </div>

        @if (auth()->user()->hasEnabledTwoFactor())
            <span class="badge-approved">Activo</span>
        @else
            <span class="badge-pending">Recomendado</span>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('panel.2fa.show') }}" class="btn-outline">
            {{ auth()->user()->hasEnabledTwoFactor() ? 'Administrar doble factor' : 'Activar doble factor' }}
        </a>

        @unless (auth()->user()->hasEnabledTwoFactor())
            <p class="text-xs text-content-muted">
                Sin doble factor, la contraseña es la única barrera de entrada.
            </p>
        @endunless
    </div>
</section>
