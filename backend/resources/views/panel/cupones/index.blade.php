<x-app-layout>
    <x-slot name="title">Cupones de descuento</x-slot>
    <x-slot name="subtitle">Creados por el auditor; descuentos porcentuales para pasajeros</x-slot>

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <a href="{{ route('panel.cupones.create') }}" class="btn-primary inline-flex items-center gap-2">
                <x-icon name="plus" class="h-4 w-4" />
                Nuevo cupón
            </a>
        </div>

        <form method="GET" action="{{ route('panel.cupones.index') }}" class="flex items-center gap-3">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por código"
                   class="form-input w-48">
            <select name="city" class="form-input">
                <option value="">Todas las ciudades</option>
                @foreach ($ciudades as $ciudad)
                    <option value="{{ $ciudad->id }}" {{ request('city') == $ciudad->id ? 'selected' : '' }}>
                        {{ $ciudad->name }}
                    </option>
                @endforeach
            </select>
            <select name="activo" class="form-input">
                <option value="">Todos</option>
                <option value="1" {{ request('activo') == '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ request('activo') == '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
            <button type="submit" class="btn-outline">
                <x-icon name="search" class="h-4 w-4" />
            </button>
        </form>
    </div>

    @if ($cupones->isEmpty())
        <div class="card p-10 text-center text-content-muted">
            Todavía no hay cupones creados.
        </div>
    @else
        <section class="card overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-surface-alt/70 text-xs uppercase tracking-wide text-content-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Código</th>
                        <th class="px-5 py-3 font-semibold">Descuento</th>
                        <th class="px-5 py-3 font-semibold">Vigencia</th>
                        <th class="px-5 py-3 font-semibold">Usos</th>
                        <th class="px-5 py-3 font-semibold">Ciudad</th>
                        <th class="px-5 py-3 font-semibold text-right">Estado</th>
                        <th class="px-5 py-3 font-semibold text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($cupones as $cupo)
                        <tr class="hover:bg-surface-alt/60 {{ $cupo->is_active ? '' : 'text-content-subtle' }}">
                            <td class="px-5 py-3 font-medium text-content">
                                {{ $cupo->code }}
                            </td>
                            <td class="px-5 py-3 font-display text-brand-700 dark:text-accent-300">
                                -{{ number_format((float) $cupo->discount_percent, 0, ',', '.') }}%
                            </td>
                            <td class="px-5 py-3 text-content-muted">
                                {{ $cupo->valid_from->format('d/m/Y') }}
                                @if ($cupo->valid_until)
                                    al {{ $cupo->valid_until->format('d/m/Y') }}
                                @else
                                    <span class="text-xs text-content-subtle ml-2">sin vencimiento</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-content-muted">
                                {{ $cupo->usages()->count() }}
                                @if ($cupo->max_uses_global)
                                    de {{ $cupo->max_uses_global }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-content-muted">
                                {{ $cupo->city?->name ?? 'Todas' }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="badge-{{ $cupo->is_active ? 'active' : 'rejected' }}">
                                    {{ $cupo->is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('panel.cupones.show', $cupo) }}" class="btn-outline btn-sm">Ver</a>
                                @if ($cupo->is_active)
                                    <form method="POST" action="{{ route('panel.cupones.destroy', $cupo) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-outline btn-sm text-content-muted"
                                                onclick="return confirm('¿Desactivar este cupón? Los viajes ya creados no cambian.')">
                                            Desactivar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($cupones->hasPages())
                <div class="border-t border-line px-5 py-4">
                    {{ $cupones->links() }}
                </div>
            @endif
        </section>
    @endif
</x-app-layout>
