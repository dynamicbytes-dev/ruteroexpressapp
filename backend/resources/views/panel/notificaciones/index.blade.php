<x-app-layout>
    <x-slot name="title">Notificaciones</x-slot>
    <x-slot name="subtitle">Avisos operativos del panel · sin correo</x-slot>

    @php
        $tipos = [
            'cuenta_por_verificar' => ['Nueva cuenta', 'users', 'badge-pending'],
            'cuenta_corregida' => ['Cuenta corregida', 'users', 'badge-pending'],
            'documento_por_verificar' => ['Documento', 'image', 'badge-pending'],
            'soporte_mensaje' => ['Soporte', 'chat', 'badge-pending'],
            'recarga_pendiente' => ['Recarga', 'wallet', 'badge-pending'],
            'retiro_pendiente' => ['Retiro', 'banknote', 'badge-pending'],
            'viaje_sin_conductor' => ['Viaje', 'route', 'badge-rejected'],
            'conductor_bloqueado' => ['Conductor', 'moto', 'badge-rejected'],
            'documentos_vencimiento' => ['Documentos', 'alert', 'badge-pending'],
        ];
    @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="card flex flex-wrap gap-1 p-1.5">
            <a href="{{ route('panel.notificaciones.index') }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $filtro === 'no_leidas' ? 'bg-brand-800 text-white' : 'text-content-muted hover:bg-line hover:text-brand-800 dark:hover:text-content' }}">
                No leídas
                @if ($noLeidas > 0)
                    <span class="rounded-full bg-amber-100 dark:bg-amber-950 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:text-amber-300">{{ $noLeidas }}</span>
                @endif
            </a>
            <a href="{{ route('panel.notificaciones.index', ['filtro' => 'todas']) }}"
               class="rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $filtro === 'todas' ? 'bg-brand-800 text-white' : 'text-content-muted hover:bg-line hover:text-brand-800 dark:hover:text-content' }}">
                Todas
            </a>
        </nav>

        @if ($noLeidas > 0)
            <form method="POST" action="{{ route('panel.notificaciones.leer-todas') }}">
                @csrf
                <button type="submit" class="btn-outline">Marcar todas como leídas</button>
            </form>
        @endif
    </div>

    <section class="card mt-4 overflow-hidden">
        @if ($notificaciones->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="bell" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">No hay notificaciones en esta lista</p>
                <p class="mt-1 text-sm text-content-muted">
                    Los eventos operativos aparecerán aquí para que el equipo los revise.
                </p>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($notificaciones as $aviso)
                    @php
                        $datos = is_array($aviso->data) ? $aviso->data : [];
                        $tipo = (string) ($datos['type'] ?? 'general');
                        [$etiqueta, $icono, $tono] = $tipos[$tipo] ?? ['Aviso', 'bell', 'badge-neutral'];
                        $sinLeer = $aviso->read_at === null;
                    @endphp

                    <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start {{ $sinLeer ? 'bg-accent-50/40 dark:bg-accent-900' : '' }}">
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $sinLeer ? 'bg-accent-100 dark:bg-accent-900 text-accent-800 dark:text-accent-300' : 'bg-line text-content-muted' }}">
                            <x-icon :name="$icono" class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="{{ $tono }}">{{ $etiqueta }}</span>
                                @if ($sinLeer)
                                    <span class="text-[11px] font-semibold uppercase tracking-wide text-accent-700 dark:text-accent-300">Sin leer</span>
                                @endif
                                <span class="text-xs text-content-subtle">{{ $aviso->created_at?->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1 font-semibold text-content">{{ $datos['title'] ?? 'Aviso operativo' }}</p>
                            <p class="mt-1 text-sm text-content-muted">{{ $datos['message'] ?? '' }}</p>
                        </div>

                        <form method="POST" action="{{ route('panel.notificaciones.leer', $aviso->id) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="btn-outline">
                                {{ ! empty($datos['url']) ? 'Abrir' : 'Marcar leída' }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>

            @if ($notificaciones->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $notificaciones->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
