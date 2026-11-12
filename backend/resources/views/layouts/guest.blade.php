<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Acceso' }} · {{ config('app.name', 'GAZ') }}</title>

        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <meta name="theme-color" content="#310F38">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-brand-900 text-white">
        @php
            // Las mismas páginas las abre el auditor (panel) y una persona desde
            // el teléfono (app). El costado de marca cambia según el caso.
            $esApp = ($contexto ?? 'panel') === 'app';

            $beneficios = $esApp
                ? [
                    'Solo se aceptan cuentas de Gmail',
                    'Documentos revisados uno por uno',
                    'Tu cartera y tus viajes en un solo lugar',
                    'Soporte por correo cuando lo necesites',
                ]
                : [
                    'Verificación de documentos de pasajeros y conductores',
                    'Aprobación de recargas por transferencia bancaria',
                    'Tarifas, recargo nocturno y tarifa dinámica',
                    'Historial completo de movimientos',
                ];
        @endphp

        <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

            {{-- Panel de marca: solo en pantallas grandes. --}}
            <div class="relative hidden flex-col justify-between overflow-hidden p-12 lg:flex"
                 style="background-image: linear-gradient(150deg, #230A29 0%, #310F38 55%, #451754 100%);">
                {{-- Resplandor cian --}}
                <div class="pointer-events-none absolute -right-24 top-1/4 h-96 w-96 rounded-full bg-accent-400/20 blur-3xl"></div>

                <div class="relative flex items-center gap-3">
                    <x-logo class="h-11 w-auto" />
                    <span class="font-display text-2xl font-semibold tracking-tight text-white">gaz</span>
                </div>

                <div class="relative max-w-md">
                    <h2 class="font-display text-4xl font-semibold leading-tight text-white">
                        {{ $esApp ? 'Tu viaje, tu ruta,' : 'Todo lo que pasa en la calle,' }}
                        <span class="text-accent-400">{{ $esApp ? 'a un toque.' : 'en un solo lugar.' }}</span>
                    </h2>
                    <p class="mt-4 text-brand-100/80">
                        @if ($esApp)
                            Pide un viaje, sigue a tu conductor en el mapa y maneja tu
                            cartera desde el teléfono.
                        @else
                            Verifica usuarios, aprueba recargas y retiros, define tarifas y
                            carga la tasa del día. Cada decisión queda registrada.
                        @endif
                    </p>

                    <ul class="mt-8 space-y-3 text-sm text-brand-100/70">
                        @foreach ($beneficios as $punto)
                            <li class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-accent-400"></span>
                                {{ $punto }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="relative text-xs text-brand-100/40">
                    {{ $esApp
                        ? '¿Necesitas ayuda? Escríbenos desde la app.'
                        : 'Panel exclusivo para auditores. Pasajeros y conductores usan la aplicación móvil.' }}
                </p>
            </div>

            {{-- Formulario --}}
            <div class="flex items-center justify-center bg-surface-alt px-6 py-12">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <x-logo class="h-10 w-auto" />
                        <span class="font-display text-xl font-semibold text-brand-900 dark:text-content">gaz</span>
                    </div>

                    <div class="card-pad">
                        {{ $slot }}
                    </div>

                    <p class="mt-6 text-center text-xs text-content-muted">
                        {{ $esApp ? 'gaz · Seguridad de la cuenta' : 'Acceso restringido · '.config('app.name').' · Auditoría' }}
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
