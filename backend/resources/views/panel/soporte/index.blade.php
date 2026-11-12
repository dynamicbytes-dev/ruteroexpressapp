<x-app-layout>
    <x-slot name="title">Soporte</x-slot>
    <x-slot name="subtitle">Conversaciones de chat con pasajeros y conductores</x-slot>

    @php
        $tintes = [
            'cyan' => 'badge-approved',
            'slate' => 'badge-neutral',
            'amber' => 'badge-pending',
            'red' => 'badge-rejected',
        ];
    @endphp

    {{-- ------------------------------------------------------------------
         Pestañas: chats abiertos e historial de conversaciones cerradas.
         ------------------------------------------------------------------ --}}
    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? 0;
                $pendiente = $clave === 'abiertas' && $total > 0;
            @endphp

            <a href="{{ route('panel.soporte.index', array_filter(['tab' => $clave, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white' : 'text-content-muted hover:bg-line hover:text-brand-800 dark:hover:text-content' }}">
                {{ $etiqueta }}

                <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                             {{ $activa ? 'bg-white/20 text-white' : ($pendiente ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300' : 'bg-line text-content-muted') }}">
                    {{ $total }}
                </span>
            </a>
        @endforeach
    </nav>

    {{-- ------------------------------------------------------------------
         Búsqueda por persona
         ------------------------------------------------------------------ --}}
    <form method="GET" action="{{ route('panel.soporte.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-content-subtle">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Nombre, cédula o correo">
            </div>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '')
            <a href="{{ route('panel.soporte.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar</a>
        @endif
    </form>

    {{-- ------------------------------------------------------------------
         Listado de conversaciones
         ------------------------------------------------------------------ --}}
    <section class="card mt-4">
        @if ($conversaciones->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="chat" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">
                    @if ($tab === 'abiertas')
                        No hay conversaciones abiertas
                    @else
                        No hay conversaciones en esta lista
                    @endif
                </p>
                <p class="mt-1 text-sm text-content-muted">
                    @if ($busqueda !== '')
                        Prueba con otra búsqueda o limpia el filtro.
                    @elseif ($tab === 'abiertas')
                        Cuando un pasajero o conductor escriba por soporte aparecerá aquí.
                    @else
                        Las conversaciones finalizadas se guardan aquí para consulta.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Persona</th>
                            <th class="px-5 py-3 font-semibold">Último mensaje</th>
                            <th class="px-5 py-3 font-semibold">Actividad</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($conversaciones as $conversacion)
                            @php
                                $usuario = $conversacion->user;
                                $ultimo = $conversacion->latestMessage;
                                $sinLeer = (int) $conversacion->unread_count;
                            @endphp
                            <tr class="hover:bg-surface-alt/60">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40 font-display text-sm font-bold text-brand-800 dark:text-content">
                                            {{ strtoupper(substr($usuario->first_name ?? 'U', 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-content">{{ $usuario?->name ?? 'Usuario eliminado' }}</div>
                                            <div class="text-xs text-content-muted">
                                                {{ $usuario?->role?->label() ?? '—' }}
                                                @if ($usuario?->city)
                                                    · {{ $usuario->city->name }}
                                                @endif
                                                @if ($usuario?->national_id)
                                                    · V-{{ $usuario->national_id }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-md px-5 py-3">
                                    <p class="truncate text-content-muted">
                                        {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $ultimo?->body) ?? ''), 90) ?: 'Sin mensajes' }}
                                    </p>
                                    @if ($sinLeer > 0)
                                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-accent-100 dark:bg-accent-900 px-2 py-0.5 text-[11px] font-semibold text-accent-900 dark:text-accent-200">
                                            {{ $sinLeer }} sin leer
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-content-muted">
                                    {{ ($conversacion->last_message_at ?? $conversacion->created_at)?->translatedFormat('d M, H:i') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $tintes[$conversacion->status->tone()] ?? 'badge-neutral' }}">
                                        {{ $conversacion->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.soporte.show', $conversacion) }}" class="btn-outline">
                                        {{ $conversacion->estaAbierta() ? 'Abrir chat' : 'Ver historial' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($conversaciones->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $conversaciones->links() }}
                </div>
            @endif
        @endif
    </section>
</x-app-layout>
