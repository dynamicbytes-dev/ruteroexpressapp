<x-app-layout>
    <x-slot name="title">Verificación en dos pasos</x-slot>
    <x-slot name="subtitle">Protege tu cuenta de auditoría con un código adicional</x-slot>

    <div class="max-w-4xl space-y-6">
        @if ($codigos !== [])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-950 p-5">
                <div class="flex items-start gap-3">
                    <x-icon name="key" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" />
                    <div class="min-w-0 flex-1">
                        <h2 class="font-display text-base font-semibold text-amber-900 dark:text-amber-300">
                            Guarda estos códigos de recuperación
                        </h2>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                            Cada uno sirve una sola vez. Guárdalos fuera del teléfono: si pierdes el
                            acceso a la aplicación de autenticación, son tu única entrada al panel.
                        </p>

                        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($codigos as $codigo)
                                <code class="rounded-lg bg-surface px-3 py-2 text-center font-mono text-sm font-semibold text-brand-900 dark:text-content">
                                    {{ $codigo }}
                                </code>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card-pad">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-xl">
                    <h2 class="panel-title">Aplicación de autenticación</h2>
                    <p class="mt-1 text-sm text-content-muted">
                        Usa Google Authenticator, Authy, 1Password o cualquier aplicación compatible
                        con códigos TOTP. Además de tu contraseña, el panel te pedirá un código de 6 dígitos.
                    </p>
                </div>

                @if ($activo)
                    <span class="badge-approved">Activo</span>
                @elseif ($pendiente)
                    <span class="badge-pending">Pendiente de confirmar</span>
                @else
                    <span class="badge-neutral">Inactivo</span>
                @endif
            </div>

            @if ($activo)
                <div class="mt-5 rounded-xl border border-accent-200 bg-accent-50 dark:bg-accent-900 px-4 py-3 text-sm text-accent-800 dark:text-accent-300">
                    El doble factor está protegiendo tu cuenta. Al iniciar sesión se pedirá el código
                    después de la contraseña.
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <form method="POST" action="{{ route('panel.2fa.recovery-codes') }}"
                          class="rounded-2xl border border-line p-4">
                        @csrf
                        <h3 class="font-display text-sm font-semibold text-brand-900 dark:text-content">Regenerar códigos de recuperación</h3>
                        <p class="mt-1 text-xs text-content-muted">
                            Los códigos anteriores dejarán de funcionar. Necesitas tu contraseña.
                        </p>
                        <div class="mt-3">
                            <x-input-label for="recovery_password" value="Contraseña actual" />
                            <x-text-input id="recovery_password" name="password" type="password"
                                          autocomplete="current-password" required />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>
                        <x-secondary-button class="mt-4">Generar códigos nuevos</x-secondary-button>
                    </form>

                    <form method="POST" action="{{ route('panel.2fa.destroy') }}"
                          class="rounded-2xl border border-red-200 p-4"
                          onsubmit="return confirm('¿Desactivar el doble factor? Tu cuenta quedará protegida solo por la contraseña.');">
                        @csrf
                        @method('DELETE')
                        <h3 class="font-display text-sm font-semibold text-red-800 dark:text-red-300">Desactivar doble factor</h3>
                        <p class="mt-1 text-xs text-content-muted">
                            Pide tu contraseña y un código válido. Después podrás volver a activarlo.
                        </p>
                        <div class="mt-3 space-y-3">
                            <div>
                                <x-input-label for="disable_password" value="Contraseña actual" />
                                <x-text-input id="disable_password" name="password" type="password"
                                              autocomplete="current-password" required />
                            </div>
                            <div>
                                <x-input-label for="disable_code" value="Código o código de recuperación" />
                                <x-text-input id="disable_code" name="code" type="text"
                                              inputmode="numeric" autocomplete="one-time-code" required />
                            </div>
                        </div>
                        <x-danger-button class="mt-4">Desactivar</x-danger-button>
                    </form>
                </div>
            @elseif ($pendiente)
                <div class="mt-6 grid gap-6 lg:grid-cols-[220px_1fr]">
                    <div class="flex items-start justify-center rounded-2xl border border-line bg-surface p-3">
                        @if ($qr)
                            <img src="{{ $qr }}" alt="Código QR para la aplicación de autenticación"
                                 class="h-48 w-48">
                        @endif
                    </div>

                    <div>
                        <h3 class="font-display text-sm font-semibold text-brand-900 dark:text-content">1. Escanea el código</h3>
                        <p class="mt-1 text-sm text-content-muted">
                            Abre tu aplicación de autenticación y escanea el QR. Si no puedes escanearlo,
                            escribe este secreto a mano:
                        </p>

                        <code class="mt-3 block break-all rounded-xl bg-surface-alt px-4 py-3 font-mono text-sm font-semibold text-brand-900 dark:text-content">
                            {{ $secreto }}
                        </code>

                        <form method="POST" action="{{ route('panel.2fa.confirm') }}" class="mt-5">
                            @csrf
                            <h3 class="font-display text-sm font-semibold text-brand-900 dark:text-content">2. Confirma con un código</h3>
                            <div class="mt-2 max-w-xs">
                                <x-input-label for="confirm_code" value="Código de 6 dígitos" />
                                <x-text-input id="confirm_code" name="code" type="text" inputmode="numeric"
                                              autocomplete="one-time-code" required placeholder="123 456" />
                                <x-input-error class="mt-2" :messages="$errors->get('code')" />
                            </div>
                            <x-primary-button class="mt-4">Activar doble factor</x-primary-button>
                        </form>
                    </div>
                </div>
            @else
                <div class="mt-5 rounded-xl border border-line bg-surface-alt px-4 py-3 text-sm text-content-muted">
                    El doble factor todavía no está activo. Recomendamos activarlo antes de publicar el panel.
                </div>

                <form method="POST" action="{{ route('panel.2fa.store') }}" class="mt-5">
                    @csrf
                    <x-primary-button>Activar doble factor</x-primary-button>
                </form>
            @endif

            @if ($errors->has('two_factor'))
                <p class="mt-4 text-sm font-medium text-red-600 dark:text-red-300">{{ $errors->first('two_factor') }}</p>
            @endif
        </div>
    </div>
</x-app-layout>
