<x-app-layout>
    <x-slot name="title">{{ $conductor->name }}</x-slot>
    <x-slot name="subtitle">Conductor · V-{{ $conductor->national_id }}</x-slot>

    <x-slot name="actions">
        <a href="{{ route('panel.conductores.index') }}" class="btn-ghost">Volver</a>
        <a href="{{ route('panel.usuarios.show', $conductor) }}" class="btn-outline">
            Documentos y cuenta
        </a>
    </x-slot>

    @php
        $perfil = $conductor->driverProfile;
        $billetera = $conductor->wallet;
        $bloqueado = $perfil?->isBlocked() ?? false;
        $deuda = (float) ($billetera?->balance ?? 0) < $tope;
        $documentos = $conductor->documents->sortBy(fn ($documento) => $documento->type->value)->values();
        $resumenDocumentos = $conductor->documentsSummary();
        $promedio = $perfil?->ratingOrNull();
        $tintes = [
            'amber' => 'badge-pending',
            'cyan' => 'badge-approved',
            'red' => 'badge-rejected',
            'slate' => 'badge-neutral',
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="card p-5">
            <p class="text-sm font-medium text-ink-600">Disponibilidad</p>
            @if ($bloqueado)
                <p class="mt-2 font-display text-xl font-semibold text-red-700">Bloqueado</p>
                <p class="mt-1 text-xs text-ink-500">
                    Hasta {{ $perfil->blocked_until->translatedFormat('d M Y, H:i') }}
                </p>
                @if ($perfil->blocked_reason)
                    <p class="mt-2 text-xs text-ink-600">{{ $perfil->blocked_reason }}</p>
                @endif
            @elseif ($perfil?->is_online)
                <p class="mt-2 font-display text-xl font-semibold text-accent-800">En línea</p>
                <p class="mt-1 text-xs text-ink-500">
                    Ubicación: {{ $perfil->last_location_at?->diffForHumans() ?? 'sin reportar' }}
                </p>
            @else
                <p class="mt-2 font-display text-xl font-semibold text-ink-500">Desconectado</p>
                <p class="mt-1 text-xs text-ink-500">No recibe alertas en este momento.</p>
            @endif
        </div>

        <div class="card p-5">
            <p class="text-sm font-medium text-ink-600">Billetera</p>
            <p class="mt-2 font-display text-2xl font-semibold {{ $deuda ? 'text-red-700' : 'text-brand-900' }}">
                ${{ number_format((float) ($billetera?->balance ?? 0), 2, '.', ',') }}
            </p>
            <p class="mt-1 text-xs text-ink-500">
                Retenido: ${{ number_format((float) ($billetera?->held_balance ?? 0), 2, '.', ',') }} ·
                Disponible: ${{ number_format($billetera?->available() ?? 0, 2, '.', ',') }}
            </p>
            @if ($deuda)
                <p class="mt-2 text-xs font-medium text-red-700">Superó el tope de ${{ number_format($tope, 2, '.', ',') }}.</p>
            @endif
        </div>

        <div class="card p-5">
            <p class="text-sm font-medium text-ink-600">Viajes completados</p>
            <p class="mt-2 font-display text-2xl font-semibold text-brand-900">{{ $resumen['completados'] }}</p>
            <p class="mt-1 text-xs text-ink-500">
                ${{ number_format($resumen['ganancias'], 2, '.', ',') }} generados ·
                {{ $resumen['cancelados'] }} cancelados
            </p>
        </div>

        <div class="card p-5">
            <p class="text-sm font-medium text-ink-600">Alertas</p>
            <p class="mt-2 font-display text-2xl font-semibold text-brand-900">
                {{ $resumen['rechazos'] + $resumen['vencidos'] }}
            </p>
            <p class="mt-1 text-xs text-ink-500">
                {{ $resumen['rechazos'] }} rechazadas · {{ $resumen['vencidos'] }} vencidas
            </p>
        </div>

        <div class="card p-5">
            <p class="text-sm font-medium text-ink-600">Calificación</p>
            @if ($promedio !== null)
                <p class="mt-2 font-display text-2xl font-semibold text-amber-600">★ {{ number_format($promedio, 1, '.', ',') }}</p>
                <p class="mt-1 text-xs text-ink-500">
                    {{ $resumen['calificaciones'] }} calificación{{ $resumen['calificaciones'] === 1 ? '' : 'es' }} ·
                    {{ $resumen['favoritos'] }} favorito{{ $resumen['favoritos'] === 1 ? '' : 's' }}
                </p>
            @else
                <p class="mt-2 font-display text-xl font-semibold text-ink-400">Sin calificaciones</p>
                <p class="mt-1 text-xs text-ink-500">
                    {{ $resumen['favoritos'] }} pasajero{{ $resumen['favoritos'] === 1 ? '' : 's' }} lo marcó favorito
                </p>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="card-pad xl:col-span-2">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="panel-title">Vehículo y contacto</h2>
                    <p class="text-xs text-ink-500">Datos declarados en el registro</p>
                </div>
                <span class="badge-neutral">{{ $conductor->role->label() }}</span>
            </header>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Nombre</dt>
                    <dd class="mt-1 text-sm font-medium text-ink-900">{{ $conductor->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Cédula</dt>
                    <dd class="mt-1 text-sm text-ink-700">V-{{ $conductor->national_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Correo</dt>
                    <dd class="mt-1 text-sm text-ink-700">{{ $conductor->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Teléfono</dt>
                    <dd class="mt-1 text-sm text-ink-700">{{ $conductor->phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Dirección</dt>
                    <dd class="mt-1 text-sm text-ink-700">{{ $conductor->address }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Vehículo</dt>
                    <dd class="mt-1 text-sm text-ink-700">
                        @if ($perfil)
                            {{ $perfil->vehicleDescription() }}
                        @else
                            Sin datos de vehículo
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Zona de operación</dt>
                    <dd class="mt-1 text-sm font-medium text-ink-900">
                        {{ $perfil?->city?->displayName() ?? 'Sin zona asignada' }}
                    </dd>
                </div>
            </dl>

            @if ($coordenadas)
                <div class="mt-5 rounded-xl border border-ink-200 bg-ink-50 px-4 py-3 text-sm text-ink-700">
                    Última ubicación:
                    <span class="font-mono">{{ number_format($coordenadas['lat'], 5, '.', '') }}, {{ number_format($coordenadas['lng'], 5, '.', '') }}</span>
                    <a href="https://www.openstreetmap.org/?mlat={{ $coordenadas['lat'] }}&mlon={{ $coordenadas['lng'] }}#map=16/{{ $coordenadas['lat'] }}/{{ $coordenadas['lng'] }}"
                       target="_blank" rel="noopener" class="ml-2 font-medium text-brand-700 underline decoration-brand-300 underline-offset-4">
                        Ver en el mapa
                    </a>
                </div>
            @endif
        </section>

        <section class="card-pad">
            <header class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="panel-title">Documentos</h2>
                    <p class="text-xs text-ink-500">
                        {{ $resumenDocumentos['approved'] }} de {{ $resumenDocumentos['required'] }} aprobados
                    </p>
                </div>
                <a href="{{ route('panel.usuarios.show', $conductor) }}" class="btn-ghost py-1.5 text-xs">Revisar</a>
            </header>

            <ul class="mt-4 space-y-3">
                @foreach ($documentos as $documento)
                    <li class="rounded-xl border border-ink-200 px-3 py-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium text-ink-800">{{ $documento->type->label() }}</p>
                                <p class="text-xs text-ink-500">
                                    @if ($documento->expires_at)
                                        Vence {{ $documento->expires_at->translatedFormat('d M Y') }}
                                    @else
                                        Sin vencimiento
                                    @endif
                                </p>
                            </div>
                            @if ($documento->isExpired())
                                <span class="badge-rejected">Vencido</span>
                            @elseif ($documento->isExpiringSoon())
                                <span class="badge-pending">{{ $documento->daysUntilExpiration() }} día(s)</span>
                            @else
                                <span class="badge-{{ $documento->status->tone() === 'cyan' ? 'approved' : ($documento->status->tone() === 'red' ? 'rejected' : 'pending') }}">
                                    {{ $documento->status->label() }}
                                </span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <section class="card-pad mt-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="panel-title">Zona de operación</h2>
                <p class="text-xs text-ink-500">Solo recibe viajes de la ciudad asignada · solo el auditor puede cambiarla</p>
            </div>
            <span class="badge-neutral">{{ $perfil?->city?->name ?? 'Sin zona' }}</span>
        </header>

        @if ($ciudades->isEmpty())
            <p class="mt-3 text-sm text-ink-500">No hay ciudades activas para asignar.</p>
        @else
            <form method="POST" action="{{ route('panel.conductores.zona', $conductor) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-64 flex-1">
                    <label for="city_id" class="form-label">Nueva zona del conductor</label>
                    <select id="city_id" name="city_id" class="form-input pr-8" required>
                        @foreach ($ciudades as $ciudad)
                            <option value="{{ $ciudad->id }}" @selected($perfil?->city_id === $ciudad->id)>
                                {{ $ciudad->displayName() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Al cambiar de zona se desconecta y se cancelan sus alertas pendientes de la zona anterior.</p>
                    @error('city_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-outline">Cambiar zona</button>
            </form>
        @endif
    </section>

    <section class="card-pad mt-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="panel-title">Estado de la cuenta</h2>
                <p class="text-xs text-ink-500">Inhabilitar impide que se conecte y reciba viajes</p>
            </div>
            <span class="{{ $tintes[$conductor->status->tone()] ?? 'badge-neutral' }}">
                {{ $conductor->status->label() }}
            </span>
        </header>

        @if ($conductor->status === \App\Enums\UserStatus::Suspended)
            <p class="mt-3 text-sm text-red-700">
                Conductor inhabilitado. No puede conectarse ni recibir alertas.
            </p>
            @if ($conductor->status_reason)
                <p class="mt-1 text-xs text-ink-600">Motivo: {{ $conductor->status_reason }}</p>
            @endif

            <form method="POST" action="{{ route('panel.conductores.habilitar', $conductor) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-accent">
                    <x-icon name="check" class="h-4 w-4" />
                    Habilitar conductor
                </button>
            </form>
        @elseif ($conductor->status === \App\Enums\UserStatus::Approved)
            <p class="mt-3 text-sm text-ink-600">
                El conductor puede activarse y recibir viajes. Si tiene un viaje en curso, lo puede terminar.
            </p>

            <form method="POST" action="{{ route('panel.conductores.inhabilitar', $conductor) }}" class="mt-4 max-w-xl">
                @csrf
                <label for="motivo-inhabilitar" class="form-label">Motivo de la inhabilitación</label>
                <textarea id="motivo-inhabilitar" name="reason" rows="3" class="form-input"
                          required minlength="10" maxlength="500"
                          placeholder="Explica qué pasó: el conductor lo va a leer en la app"></textarea>
                @error('reason')
                    <p class="form-error">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-danger mt-3">
                    <x-icon name="alert" class="h-4 w-4" />
                    Inhabilitar conductor
                </button>
            </form>
        @else
            <p class="mt-3 text-sm text-ink-600">
                La cuenta está en estado «{{ $conductor->status->label() }}»; las decisiones de verificación se toman desde
                <a href="{{ route('panel.usuarios.show', $conductor) }}" class="font-medium text-brand-700 underline decoration-brand-300 underline-offset-4">
                    Documentos y cuenta
                </a>.
            </p>
        @endif
    </section>

    <section class="card mt-6">
        <header class="border-b border-ink-100 px-5 py-4">
            <h2 class="panel-title">Calificaciones recientes</h2>
            <p class="text-xs text-ink-500">
                Promedio ★ {{ $promedio === null ? '—' : number_format($promedio, 1, '.', ',') }} ·
                {{ $resumen['calificaciones'] }} en total
            </p>
        </header>

        @if ($calificaciones->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-500">Todavía no ha recibido calificaciones.</p>
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($calificaciones as $calificacion)
                    <li class="px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-base tracking-wide text-amber-500">{{ $calificacion->stars() }}</span>
                            <span class="text-xs text-ink-400">
                                {{ $calificacion->created_at?->translatedFormat('d M Y, H:i') }}
                            </span>
                        </div>
                        @if ($calificacion->comment)
                            <p class="mt-2 text-sm text-ink-700">{{ $calificacion->comment }}</p>
                        @else
                            <p class="mt-2 text-xs text-ink-400">Sin comentario</p>
                        @endif
                        <p class="mt-1 text-xs text-ink-500">
                            Viaje #{{ $calificacion->trip_id }} ·
                            {{ $calificacion->passenger?->name ?? 'Pasajero' }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card mt-6">
        <header class="border-b border-ink-100 px-5 py-4">
            <h2 class="panel-title">Historial de verificación</h2>
            <p class="text-xs text-ink-500">Decisiones del auditor sobre documentos y cuenta</p>
        </header>

        @if ($conductor->verificationReviews->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-500">Todavía no hay decisiones registradas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Fecha</th>
                            <th class="px-5 py-3 font-semibold">Alcance</th>
                            <th class="px-5 py-3 font-semibold">Decisión</th>
                            <th class="px-5 py-3 font-semibold">Motivo</th>
                            <th class="px-5 py-3 font-semibold">Auditor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($conductor->verificationReviews->sortByDesc('created_at') as $revision)
                            @php
                                $decisiones = [
                                    'approved' => ['Aprobado', 'badge-approved'],
                                    'rejected' => ['Rechazado', 'badge-rejected'],
                                    'suspended' => ['Suspendido', 'badge-rejected'],
                                    'reactivated' => ['Reactivado', 'badge-approved'],
                                ];
                                [$etiqueta, $clase] = $decisiones[$revision->decision] ?? ['—', 'badge-neutral'];
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-5 py-3 text-ink-500">
                                    {{ $revision->created_at?->translatedFormat('d M Y, H:i') }}
                                </td>
                                <td class="px-5 py-3 text-ink-600">
                                    {{ $revision->scope === 'document' ? ($revision->document_type?->label() ?? 'Documento') : 'Cuenta' }}
                                </td>
                                <td class="px-5 py-3"><span class="{{ $clase }}">{{ $etiqueta }}</span></td>
                                <td class="px-5 py-3 text-ink-600">{{ $revision->reason ?: '—' }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ $revision->auditor?->name ?? 'Sistema' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card mt-6">
        <header class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-4">
            <div>
                <h2 class="panel-title">Últimos viajes</h2>
                <p class="text-xs text-ink-500">Como conductor asignado</p>
            </div>
            <a href="{{ route('panel.viajes.index', ['buscar' => $conductor->first_name]) }}" class="btn-ghost py-1.5 text-xs">
                Ver todos
            </a>
        </header>

        @if ($viajes->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-500">Todavía no tiene viajes.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Viaje</th>
                            <th class="px-5 py-3 font-semibold">Ruta</th>
                            <th class="px-5 py-3 font-semibold">Pasajero</th>
                            <th class="px-5 py-3 font-semibold">Ganancia</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($viajes as $viaje)
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-5 py-3 font-medium text-ink-900">#{{ $viaje->id }}</td>
                                <td class="px-5 py-3">
                                    <div class="max-w-64 truncate text-ink-700">{{ $viaje->origin_address }}</div>
                                    <div class="max-w-64 truncate text-xs text-ink-500">→ {{ $viaje->destination_address }}</div>
                                </td>
                                <td class="px-5 py-3 text-ink-600">{{ $viaje->passenger?->name ?? '—' }}</td>
                                <td class="px-5 py-3 font-semibold text-brand-800">
                                    ${{ number_format((float) $viaje->driver_earning, 2, '.', ',') }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="{{ ['amber' => 'badge-pending', 'purple' => 'badge-neutral', 'cyan' => 'badge-approved', 'green' => 'badge-approved', 'slate' => 'badge-neutral'][$viaje->status->tone()] ?? 'badge-neutral' }}">
                                        {{ $viaje->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('panel.viajes.show', $viaje) }}" class="btn-outline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card mt-6">
        <header class="border-b border-ink-100 px-5 py-4">
            <h2 class="panel-title">Últimas alertas recibidas</h2>
            <p class="text-xs text-ink-500">Respuestas y vencimientos que afectan la penalización</p>
        </header>

        @if ($ofertas->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-500">Todavía no ha recibido alertas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Viaje</th>
                            <th class="px-5 py-3 font-semibold">Enviada</th>
                            <th class="px-5 py-3 font-semibold">Distancia</th>
                            <th class="px-5 py-3 font-semibold">Respuesta</th>
                            <th class="px-5 py-3 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($ofertas as $oferta)
                            <tr>
                                <td class="px-5 py-3 font-medium text-ink-900">#{{ $oferta->trip_id }}</td>
                                <td class="px-5 py-3 text-ink-500">{{ $oferta->offered_at?->translatedFormat('d M, H:i') }}</td>
                                <td class="px-5 py-3 text-ink-600">{{ number_format((float) $oferta->distance_to_pickup_km, 2, ',', '.') }} km</td>
                                <td class="px-5 py-3 text-ink-500">{{ $oferta->responded_at?->translatedFormat('d M, H:i') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <span class="{{ ['pending' => 'badge-pending', 'accepted' => 'badge-approved', 'rejected' => 'badge-rejected', 'expired' => 'badge-neutral', 'cancelled' => 'badge-neutral'][$oferta->status->value] ?? 'badge-neutral' }}">
                                        {{ $oferta->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-app-layout>
