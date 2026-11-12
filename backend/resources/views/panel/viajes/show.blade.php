<x-app-layout>
    <x-slot name="title">Viaje #{{ $viaje->id }}</x-slot>
    <x-slot name="subtitle">{{ $viaje->status->label() }} · {{ $viaje->requested_at?->translatedFormat('d M Y, H:i') }}</x-slot>
    <x-slot name="actions">
        <a href="{{ route('panel.viajes.index') }}" class="btn-outline">Volver a viajes</a>
    </x-slot>

    @php
        $tinte = [
            'amber' => 'badge-pending',
            'purple' => 'badge-neutral',
            'cyan' => 'badge-approved',
            'green' => 'badge-approved',
            'slate' => 'badge-neutral',
        ][$viaje->status->tone()] ?? 'badge-neutral';

        $mapa = fn (float $lat, float $lng): string => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map=17/'.$lat.'/'.$lng;
    @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="card">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <h2 class="panel-title">Ruta del viaje</h2>
                        <p class="text-xs text-content-muted">
                            {{ number_format((float) $viaje->distance_km, 2, ',', '.') }} km ·
                            {{ $viaje->duration_minutes }} min estimados
                        </p>
                    </div>
                    <span class="{{ $tinte }}">{{ $viaje->status->label() }}</span>
                </header>

                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-line bg-surface-alt/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-content-muted">Recogida</p>
                        <p class="mt-2 font-medium text-content">{{ $viaje->origin_address }}</p>
                        <p class="mt-1 text-xs text-content-muted">
                            {{ number_format((float) $viaje->origin_lat, 6, ',', '.') }},
                            {{ number_format((float) $viaje->origin_lng, 6, ',', '.') }}
                        </p>
                        <a href="{{ $mapa((float) $viaje->origin_lat, (float) $viaje->origin_lng) }}"
                           target="_blank" rel="noopener" class="btn-ghost mt-3 px-0 text-brand-700 dark:text-accent-300">
                            Ver en OpenStreetMap
                        </a>
                    </div>

                    <div class="rounded-2xl border border-line bg-surface-alt/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-content-muted">Destino</p>
                        <p class="mt-2 font-medium text-content">{{ $viaje->destination_address }}</p>
                        <p class="mt-1 text-xs text-content-muted">
                            {{ number_format((float) $viaje->destination_lat, 6, ',', '.') }},
                            {{ number_format((float) $viaje->destination_lng, 6, ',', '.') }}
                        </p>
                        <a href="{{ $mapa((float) $viaje->destination_lat, (float) $viaje->destination_lng) }}"
                           target="_blank" rel="noopener" class="btn-ghost mt-3 px-0 text-brand-700 dark:text-accent-300">
                            Ver en OpenStreetMap
                        </a>
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="panel-title">Personas</h2>
                </header>

                <div class="grid gap-4 p-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-line p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-content-muted">Pasajero</p>
                        <p class="mt-2 font-medium text-content">{{ $viaje->passenger?->name ?? '—' }}</p>
                        <p class="text-sm text-content-muted">{{ $viaje->passenger?->email }}</p>
                        <p class="text-sm text-content-muted">V-{{ $viaje->passenger?->national_id }} · {{ $viaje->passenger?->phone }}</p>
                    </div>

                    <div class="rounded-2xl border border-line p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-content-muted">Conductor</p>
                        @if ($viaje->driver)
                            <p class="mt-2 font-medium text-content">{{ $viaje->driver->name }}</p>
                            <p class="text-sm text-content-muted">{{ $viaje->driver->email }}</p>
                            <p class="text-sm text-content-muted">V-{{ $viaje->driver->national_id }} · {{ $viaje->driver->phone }}</p>
                            @if ($viaje->driver->driverProfile)
                                <p class="mt-2 text-sm text-content-muted">{{ $viaje->driver->driverProfile->vehicleDescription() }}</p>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-content-subtle">Todavía sin conductor asignado.</p>
                        @endif
                    </div>
                </div>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="panel-title">Alertas enviadas</h2>
                    <p class="text-xs text-content-muted">Cascada de asignación y respuesta de cada conductor</p>
                </header>

                @if ($viaje->offers->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-content-muted">
                        No se enviaron alertas para este viaje.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                                <tr>
                                    <th class="px-5 py-3 font-semibold">Conductor</th>
                                    <th class="px-5 py-3 font-semibold">Distancia</th>
                                    <th class="px-5 py-3 font-semibold">Enviada</th>
                                    <th class="px-5 py-3 font-semibold">Respuesta</th>
                                    <th class="px-5 py-3 font-semibold">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($viaje->offers as $oferta)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <div class="font-medium text-content">{{ $oferta->driver?->name ?? '—' }}</div>
                                            <div class="text-xs text-content-muted">{{ $oferta->driver?->email }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-content-muted">
                                            {{ number_format((float) $oferta->distance_to_pickup_km, 2, ',', '.') }} km
                                        </td>
                                        <td class="px-5 py-3 text-content-muted">
                                            {{ $oferta->offered_at?->translatedFormat('d M, H:i:s') }}
                                        </td>
                                        <td class="px-5 py-3 text-content-muted">
                                            {{ $oferta->responded_at?->translatedFormat('d M, H:i:s') ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <span class="{{ $oferta->status === \App\Enums\TripOfferStatus::Accepted ? 'badge-approved' : ($oferta->status === \App\Enums\TripOfferStatus::Pending ? 'badge-pending' : 'badge-neutral') }}">
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
        </div>

        <div class="space-y-6">
            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="panel-title">Cobro</h2>
                    <p class="text-xs text-content-muted">{{ $viaje->payment_method->label() }}</p>
                </header>

                <div class="space-y-3 p-5 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">Tarifa base</span>
                        <span class="font-medium text-content">${{ number_format((float) $viaje->base_fare, 2, '.', ',') }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">{{ number_format((float) $viaje->distance_km, 2, ',', '.') }} km × ${{ number_format((float) $viaje->price_per_km, 2, '.', ',') }}</span>
                        <span class="font-medium text-content">${{ number_format((float) $viaje->distance_km * (float) $viaje->price_per_km, 2, '.', ',') }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">{{ $viaje->duration_minutes }} min × ${{ number_format((float) $viaje->price_per_minute, 2, '.', ',') }}</span>
                        <span class="font-medium text-content">${{ number_format((float) $viaje->duration_minutes * (float) $viaje->price_per_minute, 2, '.', ',') }}</span>
                    </div>
                    @if ($viaje->minimum_applied)
                        <div class="flex justify-between gap-4 text-amber-700 dark:text-amber-300">
                            <span>Se aplicó la tarifa mínima</span>
                            <span>${{ number_format((float) $viaje->minimum_fare, 2, '.', ',') }}</span>
                        </div>
                    @endif
                    @if ((float) $viaje->night_surcharge_percent > 0)
                        <div class="flex justify-between gap-4">
                            <span class="text-content-muted">Recargo nocturno</span>
                            <span class="font-medium text-content">+{{ number_format((float) $viaje->night_surcharge_percent, 0, ',', '.') }}%</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">Multiplicador dinámico</span>
                        <span class="font-medium text-content">×{{ number_format((float) $viaje->dynamic_multiplier, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-line pt-3">
                        <span class="font-semibold text-content-muted">Total cobrado</span>
                        <span class="font-display text-xl font-semibold text-brand-900 dark:text-content">${{ number_format((float) $viaje->total, 2, '.', ',') }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">Comisión GAZ ({{ number_format((float) $viaje->commission_percent, 0, ',', '.') }}%)</span>
                        <span class="font-medium text-content">${{ number_format((float) $viaje->commission_amount, 2, '.', ',') }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-content-muted">Ganancia del conductor</span>
                        <span class="font-semibold text-accent-800 dark:text-accent-300">${{ number_format((float) $viaje->driver_earning, 2, '.', ',') }}</span>
                    </div>
                    @if ($viaje->exchange_rate !== null)
                        <div class="rounded-xl bg-surface-alt px-3 py-2 text-xs text-content-muted">
                            Tasa del viaje: {{ number_format((float) $viaje->exchange_rate, 2, ',', '.') }} Bs/USD ·
                            equivalente Bs {{ number_format((float) $viaje->total * (float) $viaje->exchange_rate, 2, ',', '.') }}
                        </div>
                    @endif
                </div>
            </section>

            <section class="card">
                <header class="border-b border-line px-5 py-4">
                    <h2 class="panel-title">Cronología</h2>
                </header>

                <div class="space-y-3 p-5 text-sm">
                    @foreach ([
                        ['Pedido', $viaje->requested_at],
                        ['Aceptado', $viaje->accepted_at],
                        ['En el punto', $viaje->arrived_at],
                        ['Iniciado', $viaje->started_at],
                        ['Completado', $viaje->completed_at],
                        ['Cancelado', $viaje->cancelled_at],
                    ] as [$etiqueta, $fecha])
                        @if ($fecha)
                            <div class="flex items-start justify-between gap-3">
                                <span class="text-content-muted">{{ $etiqueta }}</span>
                                <span class="text-right font-medium text-content">{{ $fecha->translatedFormat('d M, H:i:s') }}</span>
                            </div>
                        @endif
                    @endforeach

                    @if ($viaje->cancelled_by)
                        <div class="rounded-xl bg-surface-alt px-3 py-2 text-xs text-content-muted">
                            Cancelado por {{ $viaje->cancelled_by === 'driver' ? 'el conductor' : 'el pasajero' }}
                            @if ($viaje->cancellation_reason)
                                · {{ $viaje->cancellation_reason }}
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
