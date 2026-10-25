@extends('pdf.layout')

@section('content')
    <div class="section-title">Registro inmutable de actividades y eventos del sistema</div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 14%;">Fecha y Hora</th>
                <th style="width: 18%;">Autor</th>
                <th style="width: 12%;">Módulo</th>
                <th style="width: 10%;" class="text-center">Acción</th>
                <th style="width: 26%;">Descripción del Evento</th>
                <th style="width: 20%;">Detalles y Cambios</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($actividades as $act)
                <tr>
                    <td>{{ $act->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>
                        <strong>{{ $act->causer instanceof \App\Models\User ? $act->causer->name : 'Sistema GAZ' }}</strong>
                        @if ($act->causer instanceof \App\Models\User)
                            <div class="text-muted" style="font-size: 8px;">{{ $act->causer->email }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-purple">{{ ucfirst(str_replace('_', ' ', $act->log_name ?? 'General')) }}</span>
                    </td>
                    <td class="text-center">
                        @php
                            $badgeClass = match($act->event) {
                                'created' => 'badge-green',
                                'updated' => 'badge-blue',
                                'deleted' => 'badge-red',
                                default => 'badge-yellow',
                            };
                            $badgeLabel = match($act->event) {
                                'created' => 'Creó',
                                'updated' => 'Actualizó',
                                'deleted' => 'Eliminó',
                                default => ucfirst((string) $act->event),
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </td>
                    <td>
                        <strong>{{ $act->description }}</strong>
                        @if ($act->subject_type)
                            <div class="text-muted" style="font-size: 8px;">
                                Objeto: {{ class_basename((string) $act->subject_type) }} #{{ $act->subject_id }}
                            </div>
                        @endif
                    </td>
                    <td class="font-mono text-muted" style="font-size: 8px; word-break: break-all;">
                        @if ($act->properties && $act->properties->isNotEmpty())
                            {{ json_encode($act->properties->toArray(), JSON_UNESCAPED_UNICODE) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding: 20px;">
                        No hay actividades registradas para los filtros seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
