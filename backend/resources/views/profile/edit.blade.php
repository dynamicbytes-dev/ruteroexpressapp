<x-app-layout>
    <x-slot name="title">Mi cuenta</x-slot>
    <x-slot name="subtitle">Datos de acceso y seguridad</x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="card-pad">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card-pad">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card-pad">
            @include('profile.partials.two-factor-form')
        </div>

        <div class="card-pad">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
