<section>
    <header>
        <h2 class="panel-title">Contraseña</h2>
        <p class="mt-1 text-sm text-content-muted">
            Cámbiala si crees que alguien más la conoce. Es la misma contraseña con la que
            entras al panel.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Contraseña actual" />
            <x-text-input id="update_password_current_password" name="current_password" type="password"
                          autocomplete="current-password" />
            <x-input-error class="mt-2" :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Contraseña nueva" />
            <x-text-input id="update_password_password" name="password" type="password"
                          autocomplete="new-password" />
            <p class="form-hint">Mínimo 8 caracteres, con al menos una letra y un número.</p>
            <x-input-error class="mt-2" :messages="$errors->updatePassword->get('password')" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Repite la contraseña nueva" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                          autocomplete="new-password" />
            <x-input-error class="mt-2" :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar contraseña</x-primary-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm font-medium text-accent-700 dark:text-accent-300">
                    Contraseña actualizada.
                </p>
            @endif
        </div>
    </form>
</section>
