<section>
    <header>
        <h2 class="panel-title">Datos de la cuenta</h2>
        <p class="mt-1 text-sm text-ink-500">
            Nombre, apellidos y correo con los que entras al panel.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="first_name" value="Nombre" />
                <x-text-input id="first_name" name="first_name" type="text"
                              :value="old('first_name', $user->first_name)"
                              required autofocus autocomplete="given-name" />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div>
                <x-input-label for="last_name" value="Apellido" />
                <x-text-input id="last_name" name="last_name" type="text"
                              :value="old('last_name', $user->last_name)"
                              required autocomplete="family-name" />
                <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="national_id" value="Cédula de identidad" />
                <x-text-input id="national_id" type="text"
                              :value="'V-'.$user->national_id"
                              disabled autocomplete="off" />
                <p class="mt-2 text-xs text-ink-500">
                    La cédula es de solo lectura. Si hay un error, el auditor debe corregirla con un motivo.
                </p>
            </div>

            <div>
                <x-input-label for="phone" value="Teléfono" />
                <x-text-input id="phone" name="phone" type="tel"
                              :value="old('phone', $user->phone)"
                              autocomplete="tel" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>
        </div>

        <div>
            <x-input-label for="address" value="Dirección" />
            <x-text-input id="address" name="address" type="text"
                          :value="old('address', $user->address)"
                          autocomplete="street-address" />
            <x-input-error class="mt-2" :messages="$errors->get('address')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email"
                          :value="old('email', $user->email)"
                          required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="mt-2 text-sm text-ink-600">
                    Tu correo no está verificado.
                    <button form="send-verification"
                            class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-4">
                        Reenviar el enlace de verificación
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-accent-700">
                        Se envió un nuevo enlace a tu correo.
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Guardar cambios</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm font-medium text-accent-700">
                    Cambios guardados.
                </p>
            @endif
        </div>
    </form>
</section>
