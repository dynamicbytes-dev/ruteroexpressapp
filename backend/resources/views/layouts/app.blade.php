<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Panel' }} · {{ config('app.name', 'gaz') }}</title>

        {{-- Marca --}}
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <meta name="theme-color" content="#310F38">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-ink-100">
        <div x-data="{ menuAbierto: false }" class="min-h-screen lg:flex">

            {{-- ------------------------------------------------------------
                 Barra lateral. Se oculta en móvil y se abre con el botón.
                 ------------------------------------------------------------ --}}
            <div
                x-cloak
                x-show="menuAbierto"
                x-transition.opacity
                @click="menuAbierto = false"
                class="fixed inset-0 z-30 bg-brand-950/60 lg:hidden"
            ></div>

            <aside
                x-cloak
                :class="menuAbierto ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-brand-900
                       transition-transform duration-200 lg:static lg:translate-x-0"
            >
                <div class="flex h-20 items-center gap-3 px-6">
                    <x-logo class="h-9 w-auto" />
                    <div class="leading-tight">
                        <div class="font-display text-base font-semibold text-white">gaz</div>
                        <div class="text-[11px] font-medium uppercase tracking-wider text-accent-400/80">
                            Panel de auditoría
                        </div>
                    </div>
                </div>

                <nav class="flex-1 space-y-1 px-4 py-2">
                    @php
                        // Cada entrada se activa sola según la ruta actual.
                        $secciones = [
                            ['ruta' => 'dashboard', 'patron' => 'dashboard', 'label' => 'Inicio', 'icono' => 'grid'],
                            ['ruta' => 'panel.pasajeros.index', 'patron' => 'panel.pasajeros*', 'label' => 'Pasajeros', 'icono' => 'users'],
                            ['ruta' => 'panel.conductores.index', 'patron' => 'panel.conductores*', 'label' => 'Conductores', 'icono' => 'moto'],
                            ['ruta' => 'panel.viajes.index', 'patron' => 'panel.viajes*', 'label' => 'Viajes', 'icono' => 'route'],
                            ['ruta' => 'panel.soporte.index', 'patron' => 'panel.soporte*', 'label' => 'Soporte', 'icono' => 'chat'],
                            ['ruta' => 'panel.recargas.index', 'patron' => 'panel.recargas', 'label' => 'Recargas', 'icono' => 'wallet'],
                            ['ruta' => 'panel.retiros.index', 'patron' => 'panel.retiros', 'label' => 'Retiros', 'icono' => 'banknote'],
                            ['ruta' => 'panel.reportes.index', 'patron' => 'panel.reportes*', 'label' => 'Reportes', 'icono' => 'chart'],
                            ['ruta' => 'panel.cupones.index', 'patron' => 'panel.cupones*', 'label' => 'Cupones', 'icono' => 'document'],
                            ['ruta' => 'panel.banners.index', 'patron' => 'panel.banners*', 'label' => 'Publicidad', 'icono' => 'image'],
                            ['ruta' => 'panel.tasa.index', 'patron' => 'panel.tasa*', 'label' => 'Tasa de cambio', 'icono' => 'exchange'],
                            ['ruta' => 'panel.historial.index', 'patron' => 'panel.historial*', 'label' => 'Historial', 'icono' => 'shield'],
                            ['ruta' => 'panel.auditores.index', 'patron' => 'panel.auditores*', 'label' => 'Auditores', 'icono' => 'shield'],
                            ['ruta' => 'panel.configuracion.edit', 'patron' => 'panel.configuracion', 'label' => 'Configuración', 'icono' => 'settings'],
                        ];
                    @endphp

                    @foreach ($secciones as $seccion)
                        @php
                            $activa = request()->routeIs($seccion['patron']);
                            $url = $seccion['ruta'] ? route($seccion['ruta']) : null;
                        @endphp

                        @if ($url)
                            <a href="{{ $url }}"
                               class="nav-link {{ $activa ? 'nav-link-active' : '' }}">
                                <x-icon :name="$seccion['icono']" class="h-5 w-5 shrink-0" />
                                {{ $seccion['label'] }}
                            </a>
                        @else
                            {{-- Se habilitan en las fases siguientes. --}}
                            <span class="nav-link cursor-not-allowed opacity-45" title="Disponible en una próxima versión">
                                <x-icon :name="$seccion['icono']" class="h-5 w-5 shrink-0" />
                                {{ $seccion['label'] }}
                                <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-accent-400/60">Pronto</span>
                            </span>
                        @endif
                    @endforeach
                </nav>

                <div class="border-t border-white/10 p-4">
                    <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent-400 font-display text-sm font-bold text-brand-900">
                            {{ strtoupper(substr(auth()->user()->first_name ?? 'A', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</div>
                            <div class="truncate text-xs text-brand-100/60">{{ auth()->user()->email }}</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="nav-link w-full text-brand-100/70">
                            <x-icon name="logout" class="h-5 w-5 shrink-0" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </aside>

            {{-- ------------------------------------------------------------
                 Contenido
                 ------------------------------------------------------------ --}}
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-20 items-center gap-4 border-b border-ink-200/70 bg-white/85 px-4 backdrop-blur lg:px-8">
                    <button
                        type="button"
                        @click="menuAbierto = true"
                        class="btn-ghost -ml-2 px-2.5 lg:hidden"
                        aria-label="Abrir menú"
                    >
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    <div class="min-w-0">
                        <h1 class="truncate font-display text-xl font-semibold text-brand-900">
                            {{ $title ?? 'Panel de auditoría' }}
                        </h1>
                        @isset($subtitle)
                            <p class="truncate text-sm text-ink-500">{{ $subtitle }}</p>
                        @endisset
                    </div>

                    <div class="ml-auto flex items-center gap-3">
                        @php($noLeidas = auth()->user()->unreadNotifications()->count())

                        <a
                            href="{{ route('panel.notificaciones.index') }}"
                            class="btn-ghost relative px-2.5"
                            aria-label="Notificaciones ({{ $noLeidas }} sin leer)"
                            title="Notificaciones"
                        >
                            <x-icon name="bell" class="h-5 w-5" />
                            <span
                                id="contador-notificaciones"
                                class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent-500 px-1 text-[10px] font-bold text-white {{ $noLeidas === 0 ? 'hidden' : '' }}"
                            >{{ $noLeidas > 99 ? '99+' : $noLeidas }}</span>
                        </a>

                        {{ $actions ?? '' }}
                    </div>
                </header>

                <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                    {{-- Resultado de la última acción (aprobar, rechazar, etc.). --}}
                    @if (session('status'))
                        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-accent-200 bg-accent-50 px-4 py-3">
                            <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-accent-800" />
                            <p class="text-sm font-medium text-accent-800">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                            <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                            <p class="text-sm font-medium text-red-700">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if (auth()->user()?->isAuditor() && ! auth()->user()->hasEnabledTwoFactor())
                        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <x-icon name="shield" class="h-5 w-5 shrink-0 text-amber-700" />
                            <p class="flex-1 text-sm text-amber-900">
                                Tu cuenta todavía no tiene verificación en dos pasos.
                            </p>
                            <a href="{{ route('panel.2fa.show') }}" class="btn-outline py-1.5 text-xs">
                                Activarla ahora
                            </a>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const contador = document.getElementById('contador-notificaciones');

                if (!contador) {
                    return;
                }

                const actualizar = async () => {
                    try {
                        const respuesta = await fetch('{{ route('panel.notificaciones.contador') }}', {
                            headers: { Accept: 'application/json' },
                        });

                        if (!respuesta.ok) {
                            return;
                        }

                        const datos = await respuesta.json();
                        const total = Number(datos.unread_count || 0);

                        contador.textContent = total > 99 ? '99+' : String(total);
                        contador.classList.toggle('hidden', total === 0);
                    } catch (_) {
                        // La campana no debe romper la pantalla si falla la red.
                    }
                };

                // Si el servidor de WebSocket está disponible, la campana se
                // actualiza al instante. El sondeo queda como respaldo.
                if (window.Echo) {
                    window.Echo.private('App.Models.User.{{ auth()->id() }}')
                        .listen('.notificacion.creada', actualizar);
                }

                window.setInterval(actualizar, 15000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        actualizar();
                    }
                });
            });
        </script>
    </body>
</html>
