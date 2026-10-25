<section class="space-y-6">
    <header>
        <h2 class="panel-title">Eliminar mi cuenta</h2>
        <p class="mt-1 text-sm text-ink-500">
            Al eliminar tu cuenta se borran tus datos de acceso. Las decisiones de verificación
            que tomaste quedan registradas en el historial de GAZ.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Eliminar mi cuenta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="font-display text-lg font-semibold text-brand-900">
                ¿Seguro que quieres eliminar tu cuenta?
            </h2>

            <p class="mt-2 text-sm text-ink-500">
                Esta acción no se puede deshacer. Escribe tu contraseña para confirmar.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Contraseña" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="w-full"
                    placeholder="Contraseña"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Eliminar la cuenta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
