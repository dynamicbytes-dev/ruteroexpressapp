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
                            <h2 class="font-display text-xl font-bold text-brand-900">{{ $pasajero->name }}</h2>
                            <p class="text-sm text-ink-500">
                                Pasajero registrado desde el {{ $pasajero->created_at?->translatedFormat('d \d\e F \d\e Y') }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if ($pasajero->isApproved())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Cuenta Aprobada
                                    </span>
                                @elseif ($pasajero->isSuspended())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 border border-rose-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        Cuenta Inhabilitada
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 border border-amber-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        {{ $pasajero->status->label() }}
                                    </span>
                                @endif

                                @if ($pasajero->email_verified_at)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 border border-brand-200">
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
                                    class="btn-outline text-rose-600 hover:bg-rose-50 hover:border-rose-300">
                                <x-icon name="shield" class="h-4 w-4" />
                                Inhabilitar pasajero
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Rejilla de datos personales --}}
                <div class="mt-6 grid gap-4 border-t border-ink-100 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Cédula</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-ink-900">{{ $pasajero->national_id }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Correo</div>
                        <div class="mt-1 text-sm font-medium text-ink-900 truncate" title="{{ $pasajero->email }}">{{ $pasajero->email }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Teléfono</div>
                        <div class="mt-1 font-mono text-sm font-semibold text-ink-900">{{ $pasajero->phone ?? 'Sin registrar' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Ciudad de origen</div>
                        <div class="mt-1 text-sm font-medium text-ink-900">{{ $pasajero->city?->displayName() ?? 'Sin asignar' }}</div>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Dirección habitual</div>
                        <div class="mt-1 text-sm text-ink-900">{{ $pasajero->address ?? 'No registrada' }}</div>
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
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Viajes completados</div>
                <div class="mt-2 font-display text-2xl font-bold text-brand-900">{{ $resumen['completados'] }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Gasto total en viajes</div>
                <div class="mt-2 font-display text-2xl font-bold text-emerald-700">${{ number_format($resumen['gasto_total'], 2, ',', '.') }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Viajes cancelados</div>
                <div class="mt-2 font-display text-2xl font-bold text-ink-600">{{ $resumen['cancelados'] }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-400">Conductores favoritos</div>
                <div class="mt-2 font-display text-2xl font-bold text-brand-800">{{ $resumen['favoritos'] }}</div>
            </div>
        </div>

        {{-- Historial de viajes recientes --}}
        <section class="card overflow-hidden">
            <header class="border-b border-ink-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="panel-title">Viajes recientes del pasajero</h3>
                    <p class="text-xs text-ink-500">Últimos viajes solicitados por esta cuenta</p>
                </div>
            </header>

            @if ($viajes->isEmpty())
                <div class="p-8 text-center text-sm text-ink-500">
                    Este pasajero todavía no ha realizado viajes.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
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
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($viajes as $viaje)
                                <tr class="hover:bg-ink-50/60">
                                    <td class="px-5 py-3 font-mono font-medium text-ink-900">
                                        <a href="{{ route('panel.viajes.show', $viaje) }}" class="text-brand-800 hover:underline">
                                            #{{ $viaje->id }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-ink-600">{{ $viaje->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-5 py-3 font-medium text-ink-900">
                                        @if ($viaje->driver)
                                            <a href="{{ route('panel.conductores.show', $viaje->driver) }}" class="hover:underline">
                                                {{ $viaje->driver->name }}
                                            </a>
                                        @else
                                            <span class="text-ink-400">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-ink-600">
                                        <div class="truncate max-w-xs" title="{{ $viaje->origin_address }} &rarr; {{ $viaje->destination_address }}">
                                            {{ $viaje->origin_address }} &rarr; {{ $viaje->destination_address }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">
                                            {{ $viaje->payment_method?->label() ?? 'Efectivo' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 font-display font-semibold text-brand-900">
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
                        <div class="flex items-center gap-3 rounded-xl border border-ink-100 p-3 bg-ink-50/30">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-800 font-bold">
                                {{ strtoupper(substr($favorito->first_name ?: 'C', 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('panel.conductores.show', $favorito) }}" class="font-semibold text-brand-900 hover:underline truncate block">
                                    {{ $favorito->name }}
                                </a>
                                <div class="text-xs text-ink-500">
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
        <div class="card max-w-md w-full p-6 bg-white shadow-2xl">
            <h3 class="font-display text-lg font-bold text-rose-900">Inhabilitar pasajero</h3>
            <p class="mt-1 text-sm text-ink-500">
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
