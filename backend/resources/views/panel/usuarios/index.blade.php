<x-app-layout>
    <x-slot name="title">Usuarios</x-slot>
    <x-slot name="subtitle">Verificación de documentos de pasajeros y conductores</x-slot>

    @php
        // Cada estado tiene su color en todo el panel.
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
            'slate' => 'badge-neutral',
        ];
    @endphp

    {{-- ------------------------------------------------------------------
         Pestañas de trabajo
         ------------------------------------------------------------------ --}}
    <nav class="card flex flex-wrap gap-1 p-1.5">
        @foreach ($tabs as $clave => $etiqueta)
            @php
                $activa = $tab === $clave;
                $total = $contadores[$clave] ?? null;
                $pendiente = in_array($clave, ['cola', 'rechazados', 'documentos'], true) && ($total ?? 0) > 0;
            @endphp

            <a href="{{ route('panel.usuarios.index', array_filter(['tab' => $clave, 'rol' => $rol?->value, 'buscar' => $busqueda ?: null])) }}"
               class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition
                      {{ $activa ? 'bg-brand-800 text-white' : 'text-ink-600 hover:bg-ink-100 hover:text-brand-800' }}">
                {{ $etiqueta }}

                @if ($total !== null)
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                 {{ $activa ? 'bg-white/20 text-white' : ($pendiente ? 'bg-amber-100 text-amber-800' : 'bg-ink-100 text-ink-500') }}">
                        {{ $total }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- ------------------------------------------------------------------
         Filtros
         ------------------------------------------------------------------ --}}
    <form method="GET" action="{{ route('panel.usuarios.index') }}" class="mt-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="min-w-64 flex-1">
            <label for="buscar" class="form-label">Buscar</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-400">
                    <x-icon name="search" class="h-5 w-5" />
                </span>
                <input id="buscar" name="buscar" type="search" value="{{ $busqueda }}"
                       class="form-input pl-10" placeholder="Nombre, cédula, correo o teléfono">
            </div>
        </div>

        <div>
            <label for="rol" class="form-label">Tipo de cuenta</label>
            <select id="rol" name="rol" class="form-input pr-8">
                <option value="">Todas</option>
                <option value="passenger" @selected($rol?->value === 'passenger')>Pasajeros</option>
                <option value="driver" @selected($rol?->value === 'driver')>Conductores</option>
            </select>
        </div>

        <button type="submit" class="btn-primary">Filtrar</button>

        @if ($busqueda !== '' || $rol !== null)
            <a href="{{ route('panel.usuarios.index', ['tab' => $tab]) }}" class="btn-ghost">Limpiar</a>
        @endif
    </form>

    {{-- ------------------------------------------------------------------
         Listado
         ------------------------------------------------------------------ --}}
    <section class="card mt-4"
             x-data="{
                 modalEdicion: false,
                 usuarioEditando: { id: '', first_name: '', last_name: '', phone: '', status: '', url: '' },
                 abrirEdicion(datos) {
                     this.usuarioEditando = datos;
                     this.modalEdicion = true;
                 }
             }">
        @if ($usuarios->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-ink-100 text-ink-400">
                    <x-icon name="users" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900">No hay nadie en esta lista</p>
                <p class="mt-1 text-sm text-ink-500">
                    @if ($busqueda !== '' || $rol !== null)
                        Prueba con otro filtro o limpia la búsqueda.
                    @else
                        Cuando alguien envíe sus documentos aparecerá aquí.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Persona</th>
                            <th class="px-5 py-3 font-semibold">Cuenta</th>
                            <th class="px-5 py-3 font-semibold">Documentos</th>
                            <th class="px-5 py-3 font-semibold">Enviado</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($usuarios as $usuario)
                            @php
                                $requeridos = count($usuario->role->requiredDocuments());
                                $rechazados = $usuario->rejected_documents_count;
                                $pendientes = $usuario->pending_documents_count;
                            @endphp
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink-900">{{ $usuario->name }}</div>
                                    <div class="text-xs text-ink-500">V-{{ $usuario->national_id }} · {{ $usuario->email }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="badge-neutral">{{ $usuario->role->label() }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-ink-700">{{ $usuario->documents_count }} de {{ $requeridos }}</span>
                                    @if ($rechazados > 0)
                                        <span class="badge-rejected ml-2">{{ $rechazados }} rechazado{{ $rechazados === 1 ? '' : 's' }}</span>
                                    @elseif ($usuario->documents_count < $requeridos)
                                        <span class="badge-neutral ml-2">Incompleto</span>
                                    @elseif ($pendientes > 0 && $usuario->status === \App\Enums\UserStatus::Approved)
                                        <span class="badge-pending ml-2">{{ $pendientes }} por revisar</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-ink-500">
                                    {{ $usuario->submitted_at?->translatedFormat('d M, H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $tintes[$usuario->status->tone()] ?? 'badge-neutral' }}">
                                        {{ $usuario->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <a href="{{ route('panel.usuarios.show', $usuario) }}" class="btn-outline !py-1 !px-3 text-xs">
                                            Revisar
                                        </a>

                                        @if (auth()->user()->is_main_auditor || auth()->user()->isOwner())
                                            <button
                                                type="button"
                                                @click="abrirEdicion({
                                                    id: {{ $usuario->id }},
                                                    first_name: @js($usuario->first_name),
                                                    last_name: @js($usuario->last_name),
                                                    phone: @js($usuario->phone),
                                                    status: @js($usuario->status->value),
                                                    url: @js(route('panel.usuarios.update', $usuario))
                                                })"
                                                class="btn-secondary !py-1 !px-3 text-xs inline-flex items-center gap-1.5 shadow-sm"
                                                title="Editar usuario"
                                            >
                                                <x-icon name="pencil" class="h-3.5 w-3.5 text-brand-700" />
                                                <span>Editar</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($usuarios->hasPages())
                <div class="border-t border-ink-100 px-5 py-4">
                    {{ $usuarios->links() }}
                </div>
            @endif
        @endif

        {{-- Modal de Edición de Usuario (Exclusivo para Auditor Principal) --}}
        @if (auth()->user()->isOwner() || auth()->user()->is_main_auditor)
            <div x-cloak x-show="modalEdicion"
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/75 backdrop-blur-sm">
                <div @click.away="modalEdicion = false"
                     x-transition.scale.95
                     class="w-full max-w-md rounded-2xl bg-brand-900 border border-brand-800 p-6 text-white shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10">
                        <h3 class="font-display font-semibold text-lg text-white">Editar Usuario</h3>
                        <button type="button" @click="modalEdicion = false" class="text-white/40 hover:text-white text-xl leading-none">&times;</button>
                    </div>

                    <form :action="usuarioEditando.url" method="POST" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-white/80">Nombre</label>
                                <input type="text" name="first_name" x-model="usuarioEditando.first_name" required
                                       class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                            </div>
                            <div>
                                <label class="form-label text-white/80">Apellido</label>
                                <input type="text" name="last_name" x-model="usuarioEditando.last_name" required
                                       class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-white/80">Teléfono</label>
                            <input type="text" name="phone" x-model="usuarioEditando.phone" required
                                   class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                        </div>

                        <div>
                            <label class="form-label text-white/80">Estatus de la cuenta</label>
                            <select name="status" x-model="usuarioEditando.status" required
                                    class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                                @foreach (\App\Enums\UserStatus::cases() as $estado)
                                    <option value="{{ $estado->value }}">{{ $estado->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pt-4 border-t border-white/10 flex justify-end gap-2">
                            <button type="button" @click="modalEdicion = false" class="btn-outline !py-1.5 !px-4 text-xs">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-accent !py-1.5 !px-4 text-xs">
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>
</x-app-layout>
