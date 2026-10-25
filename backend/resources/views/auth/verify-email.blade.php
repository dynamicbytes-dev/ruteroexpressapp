<x-guest-layout :title="'Verifica tu correo'">
    <div class="mb-6">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-700">
            <x-icon name="mail" class="h-6 w-6" />
        </span>

        <h1 class="mt-4 font-display text-2xl font-semibold text-brand-900">Confirma tu correo</h1>
        <p class="mt-1 text-sm text-ink-500">
            Te enviamos un enlace a <span class="font-medium text-ink-700">{{ auth()->user()->email }}</span>.
            Ábrelo para confirmar la dirección: es la vía por la que puedes recuperar tu
            contraseña más adelante.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-accent-200 bg-accent-50 px-4 py-3">
            <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-accent-800" />
            <p class="text-sm font-medium text-accent-800">
                Te enviamos un enlace nuevo a tu correo.
            </p>
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button type="submit">
                Reenviar el enlace
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-ghost">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
