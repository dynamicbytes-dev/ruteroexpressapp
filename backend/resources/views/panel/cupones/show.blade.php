<x-app-layout>
    <x-slot name="title">Cupón · {{ $cupo->code }}</x-slot>
    <x-slot name="subtitle">Detalle y usos del cupón</x-slot>

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="card p-5 xl:col-span-1">
            <h2 class="panel-title">Detalle</h2>

            <table class="w-full text-left text-sm">
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Código</td>
                    <td class="px-5 py-3 font-medium text-ink-900">{{ $cupo->code }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Descuento</td>
                    <td class="px-5 py-3 font-display text-brand-700 text-base font-semibold">
                        -{{ number_format((float) $cupo->discount_percent, 0, ',', '.') }}%
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Compra mínima</td>
                    <td class="px-5 py-3 text-ink-600">
                        {{ $cupo->min_purchase !== null ? '$'.number_format((float) $cupo->min_purchase, 2) : '—' }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Descuento máximo</td>
                    <td class="px-5 py-3 text-ink-600">
                        {{ $cupo->max_discount !== null ? '$'.number_format((float) $cupo->max_discount, 2) : '—' }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Vigente desde</td>
                    <td class="px-5 py-3 text-ink-600">{{ $cupo->valid_from->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Vigente hasta</td>
                    <td class="px-5 py-3 text-ink-600">
                        {{ $cupo->valid_until ? $cupo->valid_until->format('d/m/Y') : 'Sin vencimiento' }}
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Ciudad</td>
                    <td class="px-5 py-3 text-ink-600">{{ $cupo->city?->name ?? 'Todas' }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Estado</td>
                    <td class="px-5 py-3">
                        <span class="badge-{{ $cupo->is_active ? 'active' : 'rejected' }}">
                            {{ $cupo->is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Creado por</td>
                    <td class="px-5 py-3 text-ink-600">{{ $cupo->creator?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="px-5 py-3 text-ink-500 text-xs uppercase">Registrado</td>
                    <td class="px-5 py-3 text-ink-600">{{ $cupo->created_at->translatedFormat('d M Y, H:i') }}</td>
                </tr>
            </table>

            <div class="mt-5">
                <a href="{{ route('panel.cupones.edit', $cupo) }}" class="btn-outline btn-sm">Editar</a>
                @if ($cupo->is_active)
                    <form method="POST" action="{{ route('panel.cupones.destroy', $cupo) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-outline btn-sm text-ink-500"
                                onclick="return confirm('¿Desactivar este cupón? Los viajes ya creados no cambian.')">
                            Desactivar
                        </button>
                    </form>
                @endif
            </div>
        </section>

        <section class="card p-5 xl:col-span-2">
            <h2 class="panel-title">Usos del cupón</h2>
            <p class="text-xs text-ink-500 mb-4">
                Total: {{ $cupo->usages()->count() }}
                {{ $cupo->max_uses_global ? 'de '.$cupo->max_uses_global : '' }}
            </p>

            @if ($cupo->usages()->exists())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-ink-50/70 text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Usuario</th>
                                <th class="px-5 py-3 font-semibold">Viaje</th>
                                <th class="px-5 py-3 font-semibold text-right">Fecha</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($cupo->usages as $uso)
                                <tr class="hover:bg-ink-50/60">
                                    <td class="px-5 py-3 text-ink-900">
                                        {{ $uso->user->name }}
                                        <div class="text-xs text-ink-500">V-{{ $uso->user->national_id }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-ink-600">
                                        {{ $uso->trip?->id ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-right text-ink-600">
                                        {{ $uso->used_at->translatedFormat('d M Y, H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="px-5 py-10 text-center text-sm text-ink-500">
                    Nadie ha usado este cupón todavía.
                </p>
            @endif
        </section>
    </div>
</x-app-layout>
