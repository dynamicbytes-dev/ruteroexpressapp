@extends('pdf.layout')

@section('content')
    <div class="section-title">Libro mayor y movimientos de billetera en el período</div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 7%;"># ID</th>
                <th style="width: 14%;">Fecha y Hora</th>
                <th style="width: 20%;">Usuario</th>
                <th style="width: 16%;">Tipo de Movimiento</th>
                <th style="width: 10%;" class="text-center">Sentido</th>
                <th style="width: 11%;" class="text-right">Monto</th>
                <th style="width: 11%;" class="text-right">Saldo Posterior</th>
                <th style="width: 11%;">Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $mov)
                <tr>
                    <td class="font-mono text-muted">#{{ $mov->id }}</td>
                    <td>{{ $mov->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <strong>{{ $mov->wallet?->user?->name ?? 'Usuario' }}</strong>
                    </td>
                    <td>
                        {{ $mov->type?->label() ?? ucfirst((string) $mov->type) }}
                    </td>
                    <td class="text-center">
                        @if ($mov->direction === 'credit')
                            <span class="badge badge-green">Entrada (+)</span>
                        @else
                            <span class="badge badge-yellow">Salida (-)</span>
                        @endif
                    </td>
                    <td class="text-right font-mono" style="font-weight: bold; color: {{ $mov->direction === 'credit' ? '#03543f' : '#9b1c1c' }};">
                        {{ $mov->direction === 'credit' ? '+' : '-' }}${{ number_format((float) $mov->amount, 2, ',', '.') }}
                    </td>
                    <td class="text-right font-mono">
                        ${{ number_format((float) $mov->balance_after, 2, ',', '.') }}
                    </td>
                    <td class="text-muted" style="font-size: 8.5px;">
                        {{ \Illuminate\Support\Str::limit($mov->description, 28) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted" style="padding: 20px;">
                        No se registraron movimientos de billetera en este rango de fechas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
