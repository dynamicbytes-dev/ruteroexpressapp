<x-app-layout>
    <x-slot name="title">Auditores</x-slot>
    <x-slot name="subtitle">Cuentas con acceso al panel · solo el auditor principal puede registrar a otros</x-slot>

    <x-slot name="actions">
        @if ($puedeCrear || auth()->user()->is_main_auditor)
            <a href="{{ route('panel.auditores.rendimiento') }}" class="btn-outline">
                <x-icon name="chart" class="h-4 w-4" />
                Rendimiento
            </a>
            <a href="{{ route('panel.auditores.create') }}" class="btn-primary">
                <x-icon name="users" class="h-4 w-4" />
                Nuevo auditor
            </a>
        @endif
    </x-slot>

    <section class="card"
             x-data="{
                 modalEdicion: false,
                 auditorEditando: { id: '', first_name: '', last_name: '', phone: '', status: '', is_owner: '0', url: '' },
                 abrirEdicion(datos) {
                     this.auditorEditando = {
                         id: datos.id,
                         first_name: datos.first_name,
                         last_name: datos.last_name,
                         phone: datos.phone || '',
                         status: datos.status,
                         is_owner: datos.is_owner ? '1' : '0',
                         url: datos.url
                     };
                     this.modalEdicion = true;
                 }
             }">
        @if ($auditores->isEmpty())
            <div class="px-5 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-line text-content-subtle">
                    <x-icon name="shield" class="h-6 w-6" />
                </span>
                <p class="mt-3 font-display text-base font-semibold text-brand-900 dark:text-content">Todavía no hay auditores</p>
                <p class="mt-1 text-sm text-content-muted">
                    {{ $puedeCrear ? 'Crea el primero con el botón «Nuevo auditor».' : 'El auditor principal todavía no ha creado otras cuentas.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Auditor</th>
                            <th class="px-5 py-3 font-semibold">Contacto</th>
                            <th class="px-5 py-3 font-semibold">Estatus</th>
                            <th class="px-5 py-3 font-semibold">Doble factor</th>
                            <th class="px-5 py-3 font-semibold">Creado</th>
                            @if ($puedeCrear || auth()->user()->is_main_auditor)
                                <th class="px-5 py-3 text-right"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($auditores as $auditor)
                            <tr class="hover:bg-surface-alt/60">
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-content">{{ $auditor->name }}</span>
                                        @if ($auditor->isOwner())
                                            <span class="badge-approved">Auditor principal</span>
                                        @else
                                            <span class="badge-neutral">Auditor secundario</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-content-muted">V-{{ $auditor->national_id }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="text-content-muted">{{ $auditor->email }}</div>
                                    <div class="text-xs text-content-muted">{{ $auditor->phone ?? 'Sin teléfono' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ $auditor->status === \App\Enums\UserStatus::Approved ? 'badge-approved' : 'badge-rejected' }}">
                                        {{ $auditor->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($auditor->hasEnabledTwoFactor())
                                        <span class="badge-approved">Activado</span>
                                    @else
                                        <span class="badge-pending">Pendiente</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-content-muted">
                                    {{ $auditor->created_at?->translatedFormat('d M Y') }}
                                </td>
                                @if ($puedeCrear || auth()->user()->is_main_auditor)
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            type="button"
                                            @click="abrirEdicion({
                                                id: {{ $auditor->id }},
                                                first_name: @js($auditor->first_name),
                                                last_name: @js($auditor->last_name),
                                                phone: @js($auditor->phone ?? ''),
                                                status: @js($auditor->status->value),
                                                is_owner: @js($auditor->isOwner()),
                                                url: @js(route('panel.auditores.update', $auditor))
                                            })"
                                            class="btn-secondary !py-1 !px-3 text-xs inline-flex items-center gap-1.5 shadow-sm"
                                            title="Editar datos del auditor"
                                        >
                                            <x-icon name="pencil" class="h-3.5 w-3.5 text-brand-700 dark:text-accent-300" />
                                            <span>Editar</span>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($auditores->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $auditores->links() }}
                </div>
            @endif
        @endif

        {{-- Modal de Edición de Auditor (Exclusivo para Auditor Principal) --}}
        @if ($puedeCrear || auth()->user()->is_main_auditor)
            <div x-cloak x-show="modalEdicion"
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-950/75 backdrop-blur-sm">
                <div @click.away="modalEdicion = false"
                     x-transition.scale.95
                     class="w-full max-w-md rounded-2xl bg-brand-900 border border-brand-800 p-6 text-white shadow-2xl">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-400/20 text-accent-400">
                                <x-icon name="shield" class="h-4 w-4" />
                            </span>
                            <h3 class="font-display font-semibold text-lg text-white">Editar Auditor</h3>
                        </div>
                        <button type="button" @click="modalEdicion = false" class="text-white/40 hover:text-white text-xl leading-none">&times;</button>
                    </div>

                    <form :action="auditorEditando.url" method="POST" class="mt-4 space-y-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-white/80">Nombre</label>
                                <input type="text" name="first_name" x-model="auditorEditando.first_name" required
                                       class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                            </div>
                            <div>
                                <label class="form-label text-white/80">Apellido</label>
                                <input type="text" name="last_name" x-model="auditorEditando.last_name" required
                                       class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-white/80">Teléfono</label>
                            <input type="text" name="phone" x-model="auditorEditando.phone" required
                                   class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400"
                                   placeholder="0412-1234567">
                        </div>

                        <div>
                            <label class="form-label text-white/80">Nivel / Rango jerárquico</label>
                            <select name="is_owner" x-model="auditorEditando.is_owner"
                                    class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                                <option value="1">Auditor Principal (Master / Clase A)</option>
                                <option value="0">Auditor Secundario (Verificación y lectura)</option>
                            </select>
                            <p class="mt-1 text-xs text-white/50">
                                Los auditores principales pueden crear y editar a otros auditores.
                            </p>
                        </div>

                        <div>
                            <label class="form-label text-white/80">Estatus de la cuenta</label>
                            <select name="status" x-model="auditorEditando.status" required
                                    class="form-input bg-brand-950 text-white border-white/10 focus:border-accent-400">
                                <option value="{{ \App\Enums\UserStatus::Approved->value }}">Habilitado / Activo</option>
                                <option value="{{ \App\Enums\UserStatus::Suspended->value }}">Suspendido</option>
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
