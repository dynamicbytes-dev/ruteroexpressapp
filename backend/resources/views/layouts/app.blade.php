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

        {{-- Tema persistente: se aplica antes del primer pintado para evitar
             el destello claro al entrar en oscuro. --}}
        <script>
            (() => {
                try {
                    const guardado = localStorage.getItem('gaz2027-tema');
                    const oscuro = guardado === 'dark'
                        || (!guardado && window.matchMedia('(prefers-color-scheme: dark)').matches);

                    document.documentElement.classList.toggle('dark', oscuro);
                    document.documentElement.dataset.theme = oscuro ? 'dark' : 'light';
                } catch (_) {
                    document.documentElement.dataset.theme = 'light';
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-surface-alt dark:bg-ink-950">
        @php
            $grupos = [
                'Operación' => [
                    ['ruta' => 'dashboard', 'patron' => 'dashboard', 'label' => 'Inicio', 'icono' => 'grid'],
                    ['ruta' => 'panel.pasajeros.index', 'patron' => 'panel.pasajeros*', 'label' => 'Pasajeros', 'icono' => 'users'],
                    ['ruta' => 'panel.conductores.index', 'patron' => 'panel.conductores*', 'label' => 'Conductores', 'icono' => 'moto'],
                    ['ruta' => 'panel.viajes.index', 'patron' => 'panel.viajes*', 'label' => 'Viajes', 'icono' => 'route'],
                    ['ruta' => 'panel.soporte.index', 'patron' => 'panel.soporte*', 'label' => 'Soporte', 'icono' => 'chat'],
                ],
                'Dinero' => [
                    ['ruta' => 'panel.recargas.index', 'patron' => 'panel.recargas', 'label' => 'Recargas', 'icono' => 'wallet'],
                    ['ruta' => 'panel.retiros.index', 'patron' => 'panel.retiros', 'label' => 'Retiros', 'icono' => 'banknote'],
                    ['ruta' => 'panel.reportes.index', 'patron' => 'panel.reportes*', 'label' => 'Reportes', 'icono' => 'chart'],
                    ['ruta' => 'panel.tasa.index', 'patron' => 'panel.tasa*', 'label' => 'Tasa de cambio', 'icono' => 'exchange'],
                ],
                'Contenido' => [
                    ['ruta' => 'panel.cupones.index', 'patron' => 'panel.cupones*', 'label' => 'Cupones', 'icono' => 'document'],
                    ['ruta' => 'panel.banners.index', 'patron' => 'panel.banners*', 'label' => 'Publicidad', 'icono' => 'image'],
                ],
                'Sistema' => [
                    ['ruta' => 'panel.historial.index', 'patron' => 'panel.historial*', 'label' => 'Historial', 'icono' => 'shield'],
                    ['ruta' => 'panel.auditores.index', 'patron' => 'panel.auditores*', 'label' => 'Auditores', 'icono' => 'shield'],
                    ['ruta' => 'panel.configuracion.edit', 'patron' => 'panel.configuracion', 'label' => 'Configuración', 'icono' => 'settings'],
                ],
            ];

            $comandos = collect($grupos)
                ->flatMap(fn ($items, $grupo) => collect($items)
                    ->filter(fn ($item) => $item['ruta'])
                    ->map(fn ($item) => [
                        'label' => $item['label'],
                        'grupo' => $grupo,
                        'url' => route($item['ruta']),
                    ]))
                ->values();
        @endphp

        <div
            x-data="{
                menuAbierto: false,
                paleta: false,
                busqueda: '',
                oscuro: false,
                comandos: @js($comandos ?? []),
                normalizar(texto) {
                    return texto
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLowerCase();
                },
                get filtrados() {
                    const q = this.normalizar(this.busqueda.trim());
                    if (!q) return this.comandos;
                    return this.comandos.filter((c) =>
                        this.normalizar(c.label + ' ' + c.grupo).includes(q)
                    );
                },
                abrirPaleta() {
                    this.paleta = true;
                    this.$nextTick(() => this.$refs.campoPaleta?.focus());
                },
                irAlPrimero() {
                    const primero = this.filtrados[0];
                    if (primero) window.location.href = primero.url;
                },
                alternarTema() {
                    this.oscuro = !this.oscuro;
                    document.documentElement.classList.toggle('dark', this.oscuro);
                    document.documentElement.dataset.theme = this.oscuro ? 'dark' : 'light';
                    try {
                        localStorage.setItem('gaz2027-tema', this.oscuro ? 'dark' : 'light');
                    } catch (_) {}
                },
                init() {
                    this.oscuro = document.documentElement.classList.contains('dark');
                },
            }"
            @keydown.window="
                if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    abrirPaleta();
                }
                if (event.key === 'Escape') {
                    paleta = false;
                }
            "
            class="min-h-screen lg:flex"
        >

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
                <div class="flex h-16 items-center gap-3 px-6">
                    <x-logo class="h-8 w-auto" />
                    <div class="leading-tight">
                        <div class="font-display text-base font-semibold text-white">gaz</div>
                        <div class="text-[11px] font-medium uppercase tracking-wider text-accent-400/80">
                            Panel de auditoría
                        </div>
                    </div>
                </div>

                <nav class="flex-1 space-y-5 overflow-y-auto px-4 py-3">
                    @foreach ($grupos as $grupo => $secciones)
                        <div>
                            <p class="px-3 pb-1.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-brand-100/40">
                                {{ $grupo }}
                            </p>
                            <div class="space-y-1">
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
                                        <span class="nav-link cursor-not-allowed opacity-45" title="Disponible en una próxima versión">
                                            <x-icon :name="$seccion['icono']" class="h-5 w-5 shrink-0" />
                                            {{ $seccion['label'] }}
                                            <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-accent-400/60">Pronto</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="border-t border-white/10 p-4">
                    <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent-400 font-display text-sm font-bold text-brand-900 dark:text-brand-900">
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
                <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-line bg-surface/85 px-4 backdrop-blur lg:px-8">
                    <button
                        type="button"
                        @click="menuAbierto = true"
                        class="btn-ghost -ml-2 px-2.5 lg:hidden"
                        aria-label="Abrir menú"
                    >
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    <div class="min-w-0">
                        <h1 class="truncate font-display text-xl font-semibold text-content">
                            {{ $title ?? 'Panel de auditoría' }}
                        </h1>
                        @isset($subtitle)
                            <p class="truncate text-sm text-content-muted">{{ $subtitle }}</p>
                        @endisset
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <button
                            type="button"
                            @click="abrirPaleta()"
                            class="btn-outline hidden min-h-0 gap-3 rounded-panel px-3 py-2 text-xs text-content-muted sm:inline-flex"
                            aria-label="Buscar en el panel"
                        >
                            <x-icon name="search" class="h-4 w-4" />
                            <span>Buscar</span>
                            <kbd class="rounded-md border border-line bg-surface-alt px-1.5 py-0.5 font-sans text-[10px] font-semibold text-content-subtle">⌘K</kbd>
                        </button>

                        <button
                            type="button"
                            @click="alternarTema()"
                            class="btn-ghost relative px-2.5"
                            :aria-label="oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
                            :title="oscuro ? 'Tema claro' : 'Tema oscuro'"
                        >
                            <x-icon x-cloak x-show="!oscuro" name="moon" class="h-5 w-5" />
                            <x-icon x-cloak x-show="oscuro" name="sun" class="h-5 w-5" />
                        </button>

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
                        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-accent-200 bg-accent-50 dark:bg-accent-900 px-4 py-3">
                            <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-accent-800 dark:text-accent-300" />
                            <p class="text-sm font-medium text-accent-800 dark:text-accent-300">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 dark:bg-red-950 px-4 py-3">
                            <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-300" />
                            <p class="text-sm font-medium text-red-700 dark:text-red-300">{{ session('error') }}</p>
                        </div>
                    @endif

                    @if (auth()->user()?->isAuditor() && ! auth()->user()->hasEnabledTwoFactor())
                        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 dark:bg-amber-950 px-4 py-3">
                            <x-icon name="shield" class="h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" />
                            <p class="flex-1 text-sm text-amber-900 dark:text-amber-300">
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

            {{-- Paleta de comandos global (⌘K / Ctrl+K). --}}
            <div
                x-cloak
                x-show="paleta"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]"
                role="dialog"
                aria-modal="true"
                aria-label="Buscar en el panel"
            >
                <div class="absolute inset-0 bg-brand-950/60 backdrop-blur-sm" @click="paleta = false"></div>

                <div class="relative w-full max-w-xl overflow-hidden rounded-panel-lg border border-line bg-surface shadow-floating">
                    <div class="flex items-center gap-3 border-b border-line px-4">
                        <x-icon name="search" class="h-5 w-5 shrink-0 text-content-subtle" />
                        <input
                            x-ref="campoPaleta"
                            x-model="busqueda"
                            @keydown.enter.prevent="irAlPrimero()"
                            type="search"
                            placeholder="Buscar pantalla, sección o acción…"
                            class="h-14 min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-content placeholder:text-content-subtle focus:ring-0"
                            autocomplete="off"
                        >
                        <button type="button" class="btn-ghost min-h-0 px-2 py-1 text-xs" @click="paleta = false">
                            Esc
                        </button>
                    </div>

                    <div class="max-h-[52vh] overflow-y-auto p-2">
                        <template x-for="comando in filtrados" :key="comando.url">
                            <a
                                :href="comando.url"
                                @click="paleta = false"
                                class="flex items-center gap-3 rounded-panel px-3 py-2.5 text-sm text-content transition hover:bg-surface-alt"
                            >
                                <span class="flex h-8 w-8 items-center justify-center rounded-panel-sm bg-surface-alt text-content-muted">
                                    <x-icon name="arrow-right" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 flex-1 truncate" x-text="comando.label"></span>
                                <span class="text-[11px] font-medium uppercase tracking-wide text-content-subtle" x-text="comando.grupo"></span>
                            </a>
                        </template>

                        <p x-show="filtrados.length === 0" class="px-3 py-8 text-center text-sm text-content-muted">
                            No encontramos una pantalla con ese nombre.
                        </p>
                    </div>

                    <div class="flex items-center gap-4 border-t border-line bg-surface-alt px-4 py-2.5 text-[11px] text-content-subtle">
                        <span><kbd class="font-sans font-semibold">↵</kbd> abrir</span>
                        <span><kbd class="font-sans font-semibold">esc</kbd> cerrar</span>
                        <span class="ml-auto">GAZ 2027</span>
                    </div>
                </div>
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
