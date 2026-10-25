<x-app-layout>
    <x-slot name="title">Chat de soporte</x-slot>
    <x-slot name="subtitle">
        {{ $conversacion->user?->name ?? 'Usuario eliminado' }} · {{ $conversacion->status->label() }}
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('panel.soporte.index', ['tab' => $conversacion->estaAbierta() ? 'abiertas' : 'historial']) }}"
           class="btn-outline gap-2">
            <x-icon name="arrow-left" class="h-4 w-4" />
            Volver
        </a>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
        {{-- ------------------------------------------------------------------
             Hilo de la conversación
             ------------------------------------------------------------------ --}}
        <section class="card flex min-h-[34rem] flex-col overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-100 font-display text-sm font-bold text-brand-800">
                        {{ strtoupper(substr($conversacion->user?->first_name ?? 'U', 0, 1)) }}
                    </span>
                    <div>
                        <p class="font-display text-base font-semibold text-brand-900">
                            {{ $conversacion->user?->name ?? 'Usuario eliminado' }}
                        </p>
                        <p class="text-xs text-ink-500">
                            {{ $conversacion->user?->role?->label() ?? '—' }}
                            @if ($conversacion->user?->city)
                                · {{ $conversacion->user->city->name }}
                            @endif
                        </p>
                    </div>
                </div>

                <span class="{{ $conversacion->status->tone() === 'cyan' ? 'badge-approved' : 'badge-neutral' }}">
                    {{ $conversacion->status->label() }}
                </span>
            </header>

            <div id="soporte-hilo"
                 class="flex-1 space-y-3 overflow-y-auto bg-ink-50/50 px-5 py-5">
                @forelse ($conversacion->messages as $mensaje)
                    <div class="flex {{ $mensaje->esDeUsuario() ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[78%] rounded-2xl px-4 py-3 {{ $mensaje->esDeUsuario() ? 'bg-white text-ink-800 ring-1 ring-ink-100' : 'bg-brand-800 text-white' }}">
                            <p class="text-[11px] font-semibold {{ $mensaje->esDeUsuario() ? 'text-ink-400' : 'text-brand-100/70' }}">
                                {{ $mensaje->esDeUsuario() ? ($mensaje->sender?->name ?? 'Usuario') : ($mensaje->sender?->name ?? 'Soporte') }}
                                · {{ $mensaje->created_at?->translatedFormat('d M, H:i') }}
                            </p>
                            <p class="mt-1 whitespace-pre-line break-words text-sm leading-relaxed">{{ $mensaje->body }}</p>
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-ink-400 ring-1 ring-ink-100">
                            <x-icon name="chat" class="h-6 w-6" />
                        </span>
                        <p class="mt-3 font-display text-base font-semibold text-brand-900">Todavía no hay mensajes</p>
                        <p class="mt-1 text-sm text-ink-500">El usuario inició la conversación, pero aún no escribió nada.</p>
                    </div>
                @endforelse
            </div>

            {{-- Aviso que se muestra si otra persona finaliza el chat mientras
                 el auditor lo tiene abierto. --}}
            <div id="soporte-finalizada"
                 class="{{ $conversacion->estaAbierta() ? 'hidden' : '' }} border-t border-amber-200 bg-amber-50 px-5 py-4">
                <div class="flex items-start gap-3">
                    <x-icon name="check" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                    <div>
                        <p class="text-sm font-semibold text-amber-900">Conversación finalizada</p>
                        <p class="text-xs text-amber-800">
                            El chat se cerró en la app del usuario y esta conversación quedó guardada en el historial.
                            @if ($conversacion->closed_at)
                                {{ $conversacion->closed_at->translatedFormat('d M Y, H:i') }}
                            @endif
                            @if ($conversacion->closedBy)
                                · por {{ $conversacion->closedBy->name }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            @if ($conversacion->estaAbierta())
                <div id="soporte-composer" class="border-t border-ink-100 bg-white px-5 py-4">
                    <form method="POST" action="{{ route('panel.soporte.responder', $conversacion) }}" class="space-y-3">
                        @csrf

                        <div>
                            <label for="message" class="form-label">Respuesta para el usuario</label>
                            <textarea id="message" name="message" rows="3" maxlength="2000" required
                                      class="form-input resize-none @error('message') border-red-400 @enderror"
                                      placeholder="Escribe una respuesta clara y breve...">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-ink-500">
                                El usuario verá la respuesta al abrir el chat de soporte en la app.
                            </p>
                            <button type="submit" class="btn-primary gap-2">
                                <x-icon name="chat" class="h-4 w-4" />
                                Enviar respuesta
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </section>

        {{-- ------------------------------------------------------------------
             Ficha de la persona y cierre de la conversación
             ------------------------------------------------------------------ --}}
        <aside class="space-y-4">
            <section class="card p-5">
                <h2 class="panel-title">Persona</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Nombre</dt>
                        <dd class="font-medium text-ink-900">{{ $conversacion->user?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Cédula</dt>
                        <dd class="text-ink-700">{{ $conversacion->user?->national_id ? 'V-'.$conversacion->user->national_id : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Correo</dt>
                        <dd class="break-all text-ink-700">{{ $conversacion->user?->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Teléfono</dt>
                        <dd class="text-ink-700">{{ $conversacion->user?->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Ciudad</dt>
                        <dd class="text-ink-700">{{ $conversacion->user?->city?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-ink-400">Cuenta</dt>
                        <dd class="mt-1">
                            <span class="badge-neutral">{{ $conversacion->user?->role?->label() ?? '—' }}</span>
                            @if ($conversacion->user)
                                <span class="{{ $conversacion->user->status->tone() === 'cyan' ? 'badge-approved' : 'badge-pending' }} ml-1">
                                    {{ $conversacion->user->status->label() }}
                                </span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($conversacion->user)
                    <a href="{{ route('panel.usuarios.show', $conversacion->user) }}" class="btn-outline mt-4 w-full">
                        Ver ficha completa
                    </a>
                @endif
            </section>

            @if ($conversacion->estaAbierta())
                <section id="soporte-cerrar" class="card border-amber-200 bg-amber-50/60 p-5">
                    <h2 class="font-display text-base font-semibold text-amber-900">Finalizar conversación</h2>
                    <p class="mt-1 text-sm text-amber-800">
                        El chat desaparecerá de la app del usuario y podrás consultarlo en el historial del panel.
                    </p>

                    <form method="POST" action="{{ route('panel.soporte.cerrar', $conversacion) }}"
                          class="mt-4"
                          onsubmit="return confirm('¿Finalizar esta conversación? El usuario ya no verá el chat.');">
                        @csrf
                        <button type="submit" class="btn-outline w-full border-amber-300 text-amber-900 hover:bg-amber-100">
                            Dar por terminada
                        </button>
                    </form>
                </section>
            @else
                <section class="card p-5">
                    <h2 class="panel-title">Historial</h2>
                    <p class="mt-2 text-sm text-ink-600">
                        Conversación cerrada. Se conserva con todos sus mensajes para consulta del equipo.
                    </p>
                    <a href="{{ route('panel.soporte.index', ['tab' => 'historial']) }}" class="btn-outline mt-4 w-full">
                        Ver historial
                    </a>
                </section>
            @endif
        </aside>
    </div>

    @if ($conversacion->estaAbierta())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const hilo = document.getElementById('soporte-hilo');
                const compositor = document.getElementById('soporte-composer');
                const cierre = document.getElementById('soporte-cerrar');
                const finalizada = document.getElementById('soporte-finalizada');
                const url = @json(route('panel.soporte.mensajes', $conversacion));
                let firma = @json($conversacion->messages->count().':'.($conversacion->messages->last()?->id ?? 0));
                let primera = true;

                if (!hilo) {
                    return;
                }

                const pintar = (mensajes) => {
                    hilo.replaceChildren();

                    if (mensajes.length === 0) {
                        const vacio = document.createElement('div');
                        vacio.className = 'py-16 text-center text-sm text-ink-500';
                        vacio.textContent = 'Todavía no hay mensajes.';
                        hilo.appendChild(vacio);
                        return;
                    }

                    mensajes.forEach((mensaje) => {
                        const fila = document.createElement('div');
                        fila.className = 'flex ' + (mensaje.from_user ? 'justify-start' : 'justify-end');

                        const burbuja = document.createElement('div');
                        burbuja.className = 'max-w-[78%] rounded-2xl px-4 py-3 ' + (
                            mensaje.from_user
                                ? 'bg-white text-ink-800 ring-1 ring-ink-100'
                                : 'bg-brand-800 text-white'
                        );
                        burbuja.style.maxWidth = '78%';

                        const meta = document.createElement('p');
                        meta.className = 'text-[11px] font-semibold ' + (
                            mensaje.from_user ? 'text-ink-400' : 'text-brand-100/70'
                        );
                        meta.textContent = (mensaje.author || 'Soporte') + ' · ' + (mensaje.created_at_label || '');

                        const cuerpo = document.createElement('p');
                        cuerpo.className = 'mt-1 whitespace-pre-line break-words text-sm leading-relaxed';
                        cuerpo.textContent = mensaje.body || '';

                        burbuja.appendChild(meta);
                        burbuja.appendChild(cuerpo);
                        fila.appendChild(burbuja);
                        hilo.appendChild(fila);
                    });

                    hilo.scrollTop = hilo.scrollHeight;
                };

                const actualizar = async () => {
                    try {
                        const respuesta = await fetch(url, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!respuesta.ok) {
                            return;
                        }

                        const datos = await respuesta.json();

                        if (datos.status === 'closed') {
                            compositor?.classList.add('hidden');
                            cierre?.classList.add('hidden');
                            finalizada?.classList.remove('hidden');
                        }

                        if (primera || datos.signature !== firma) {
                            firma = datos.signature;
                            primera = false;
                            pintar(datos.messages || []);
                        }
                    } catch (_) {
                        // El sondeo no debe romper el chat si se cae la red.
                    }
                };

                hilo.scrollTop = hilo.scrollHeight;
                window.setInterval(actualizar, 5000);
            });
        </script>
    @endif
</x-app-layout>
