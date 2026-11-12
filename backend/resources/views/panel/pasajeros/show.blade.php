<x-app-layout>
    <x-slot name="title">{{ $pasajero->name }}</x-slot>
    <x-slot name="subtitle">Ficha de pasajero · C.I. {{ $pasajero->national_id }} · {{ $pasajero->city?->displayName() ?? 'Sin zona asignada' }}</x-slot>

    <x-slot name="actions">
        <div class="flex items-center gap-2">
            <a href="{{ route('panel.pasajeros.pdf', $pasajero) }}" class="btn-primary">
                <x-icon name="download" class="h-4 w-4" />
                Exportar Ficha PDF
            </a>

            <a href="{{ route('panel.pasajeros.edit', $pasajero) }}" class="btn-outline">
                <x-icon name="pencil" class="h-4 w-4" />
                Editar datos
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Ficha superior y acciones de estado --}}
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Datos principales --}}
            <section class="card p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-900 font-display text-2xl font-bold text-accent-400">
                            {{ strtoupper(substr($pasajero->first_name ?: 'P', 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="font-display text-xl font-bold text-brand-900 dark:text-content">{{ $pasajero->name }}</h2>
                            <p class="text-sm text-content-muted">
                                Pasajero registrado desde el {{ $pasajero->created_at?->translatedFormat('d \d\e F \d\e Y') }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($pasajero->isApproved())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-950 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Cuenta Aprobada
                                    </span>
                                @elseif ($pasajero->isSuspended())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 dark:bg-rose-950 px-2.5 py-0.5 text-xs font-medium text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        Cuenta Inhabilitada
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-950 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300 border border-amber-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        {{ $pasajero->status->label() }}
                                    </span>
                                @endif

                                @if ($pasajero->email_verified_at)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 dark:bg-brand-900/30 px-2.5 py-0.5 text-xs font-medium text-brand-700 dark:text-accent-300 border border-brand-200 dark:border-brand-700">
                                        Correo verificado
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Acciones de inhabilitar / habilitar --}}
                    <div>
                        @if ($pasajero->isSuspended())
                            <form method="POST" action="{{ route('panel.pasajeros.habilitar', $pasajero) }}"
                                  onsubmit="return confirm('¿Reactivar y habilitar a este pasajero?')">
                                @csrf
                                <button type="submit" class="btn-primary bg-emerald-600 hover:bg-emerald-700 border-emerald-600">
                                    <x-icon name="check" class="h-4 w-4" />
                                    Habilitar cuenta
                                </button>
                            </form>
                        @else
                            <button type="button"
                                    onclick="document.getElementById('modal-inhabilitar').classList.remove('hidden')"
                                    class="btn-outline text-rose-600 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950 hover:border-rose-300 dark:hover:border-rose-700">
                                <x-icon name="shield" class="h-4 w-4" />
                                Inhabilitar pasajero
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Rejilla de datos personales --}}
                <div class="mt-6 grid gap-4 border-t border-line pt-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Cédula</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-content">{{ $pasajero->national_id }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Correo</div>
                        <div class="mt-1 text-sm font-medium text-content truncate" title="{{ $pasajero->email }}">{{ $pasajero->email }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Teléfono</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-content">{{ $pasajero->phone ?? 'Sin registrar' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Ciudad de origen</div>
                        <div class="mt-1 text-sm font-medium text-content">{{ $pasajero->city?->displayName() ?? 'Sin asignar' }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Dirección habitual</div>
                        <div class="mt-1 text-sm text-content">{{ $pasajero->address ?? 'No registrada' }}</div>
                    </div>
                </div>
            </section>

            {{-- Resumen de billetera --}}
            <section class="card p-6 flex flex-col justify-between bg-gradient-to-br from-brand-900 to-brand-950 text-white">
                <div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-accent-400">Billetera GAZ</p>
                        <x-icon name="wallet" class="h-5 w-5 text-accent-400" />
                    </div>

                    <div class="mt-4">
                        <div class="text-xs text-brand-100/70">Saldo disponible</div>
                        <div class="mt-1 font-display text-3xl font-bold text-white">
                            ${{ number_format((float) ($pasajero->wallet?->balance ?? 0), 2, ',', '.') }}
                        </div>
                    </div>

                    @if (($pasajero->wallet?->held_balance ?? 0) > 0)
                        <div class="mt-3 text-xs text-brand-100/70">
                            Saldo retenido en viajes activos:
                            <span class="font-bold text-amber-300">${{ number_format((float) $pasajero->wallet->held_balance, 2, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <div class="mt-6 border-t border-white/10 pt-4 text-xs text-brand-100/70">
                    <div>Recargas aprobadas: <strong class="text-white">{{ $resumen['recargas'] }}</strong></div>
                    <div class="mt-1">Gasto acumulado: <strong class="text-white">${{ number_format($resumen['gasto_total'], 2, ',', '.') }}</strong></div>
                </div>
            </section>
        </div>

        {{-- Métricas de uso --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Viajes completados</div>
                <div class="mt-2 font-display text-2xl font-bold text-brand-900 dark:text-content">{{ $resumen['completados'] }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Gasto total en viajes</div>
                <div class="mt-2 font-display text-2xl font-bold text-emerald-700 dark:text-emerald-300">${{ number_format($resumen['gasto_total'], 2, ',', '.') }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Viajes cancelados</div>
                <div class="mt-2 font-display text-2xl font-bold text-content-muted">{{ $resumen['cancelados'] }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-content-subtle">Conductores favoritos</div>
                <div class="mt-2 font-display text-2xl font-bold text-brand-800 dark:text-content">{{ $resumen['favoritos'] }}</div>
            </div>
        </div>

        {{-- Historial de viajes recientes --}}
        <section class="card overflow-hidden">
            <header class="border-b border-line px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="panel-title">Viajes recientes del pasajero</h3>
                    <p class="text-xs text-content-muted">Últimos viajes solicitados por esta cuenta</p>
                </div>
            </header>

            @if ($viajes->isEmpty())
                <div class="p-8 text-center text-sm text-content-muted">
                    Este pasajero todavía no ha realizado viajes.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Viaje</th>
                                <th class="px-5 py-3 font-semibold">Fecha</th>
                                <th class="px-5 py-3 font-semibold">Conductor</th>
                                <th class="px-5 py-3 font-semibold">Ruta</th>
                                <th class="px-5 py-3 font-semibold">Pago</th>
                                <th class="px-5 py-3 font-semibold">Monto</th>
                                <th class="px-5 py-3 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($viajes as $viaje)
                                <tr class="hover:bg-surface-alt/60">
                                    <td class="px-5 py-3 font-mono font-medium text-content">
                                        <a href="{{ route('panel.viajes.show', $viaje) }}" class="text-brand-800 dark:text-content hover:underline">
                                            #{{ $viaje->id }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-content-muted">{{ $viaje->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3 font-medium text-content">
                                        @if ($viaje->driver)
                                            <a href="{{ route('panel.conductores.show', $viaje->driver) }}" class="hover:underline">
                                                {{ $viaje->driver->name }}
                                            </a>
                                        @else
                                            <span class="text-content-subtle">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-content-muted">
                                        <div class="truncate max-w-xs" title="{{ $viaje->origin_address }} &rarr; {{ $viaje->destination_address }}">
                                            {{ $viaje->origin_address }} &rarr; {{ $viaje->destination_address }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-md bg-purple-50 dark:bg-purple-950 px-2 py-0.5 text-xs font-medium text-purple-700 dark:text-purple-300">
                                            {{ $viaje->payment_method?->label() ?? 'Efectivo' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 font-display font-semibold text-brand-900 dark:text-content">
                                        ${{ number_format((float) $viaje->total, 2, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="badge-{{ $viaje->status->value === 'completed' ? 'approved' : ($viaje->status->value === 'cancelled' ? 'rejected' : 'pending') }}">
                                            {{ $viaje->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Conductores favoritos --}}
        @if ($pasajero->favoriteDrivers->isNotEmpty())
            <section class="card p-6">
                <h3 class="panel-title mb-4">Conductores favoritos guardados</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($pasajero->favoriteDrivers as $favorito)
                        <div class="flex items-center gap-3 rounded-xl border border-line p-3 bg-surface-alt/30">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-800 dark:text-content font-bold">
                                {{ strtoupper(substr($favorito->first_name ?: 'C', 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('panel.conductores.show', $favorito) }}" class="font-semibold text-brand-900 dark:text-content hover:underline truncate block">
                                    {{ $favorito->name }}
                                </a>
                                <div class="text-xs text-content-muted">
                                    {{ $favorito->driverProfile?->vehicle_plate ?? 'Placa N/D' }} · {{ $favorito->driverProfile?->city?->name ?? 'Sin zona' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Modal para inhabilitar --}}
    <div id="modal-inhabilitar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="card max-w-md w-full p-6 bg-surface shadow-2xl">
            <h3 class="font-display text-lg font-bold text-rose-900 dark:text-rose-300">Inhabilitar pasajero</h3>
            <p class="mt-1 text-sm text-content-muted">
                La cuenta quedará suspendida. El pasajero leerá este motivo al intentar ingresar a la aplicación.
            </p>

            <form method="POST" action="{{ route('panel.pasajeros.inhabilitar', $pasajero) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="reason" class="form-label">Motivo de inhabilitación</label>
                    <textarea id="reason" name="reason" rows="3" required minlength="8" maxlength="500"
                              class="form-input" placeholder="Ej.: Incumplimiento reiterado de normas del servicio o conducta inadecuada."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modal-inhabilitar').classList.add('hidden')" class="btn-ghost">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary bg-rose-600 hover:bg-rose-700 border-rose-600">
                        Confirmar inhabilitación
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
