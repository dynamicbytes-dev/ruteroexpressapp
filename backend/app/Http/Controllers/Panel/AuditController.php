<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auditoría del sistema.
 *
 * Muestra el `activity_log` con filtros y permite descargar la misma consulta
 * en CSV. Las decisiones de verificación también quedan en
 * `verification_reviews`; aquí se enlazan desde la ficha de cada persona.
 */
class AuditController extends Controller
{
    public const EVENTS = [
        'created' => 'Creó',
        'updated' => 'Actualizó',
        'deleted' => 'Eliminó',
    ];

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);

        $actividades = $this->consulta($filtros)
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('panel.auditoria.index', [
            'actividades' => $actividades,
            'filtros' => $filtros,
            'logs' => $this->logs(),
            'eventos' => self::EVENTS,
            'total' => $this->consulta($filtros)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $nombre = 'auditoria_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($filtros): void {
            $salida = fopen('php://output', 'w');

            // BOM para que Excel abra los acentos correctamente.
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Fecha', 'Autor', 'Correo', 'Evento', 'Módulo',
                'Descripción', 'Modelo', 'Cambios',
            ], ';');

            $this->consulta($filtros)
                ->with(['causer', 'subject'])
                ->latest()
                ->chunk(200, function (Collection $actividades) use ($salida): void {
                    foreach ($actividades as $actividad) {
                        fputcsv($salida, [
                            $actividad->created_at?->format('d/m/Y H:i:s'),
                            $actividad->causer instanceof User ? $actividad->causer->name : 'Sistema',
                            $actividad->causer instanceof User ? $actividad->causer->email : '',
                            $actividad->event ?? '',
                            $actividad->log_name ?? '',
                            $actividad->description ?? '',
                            class_basename((string) $actividad->subject_type),
                            json_encode($actividad->properties?->toArray() ?? [], JSON_UNESCAPED_UNICODE),
                        ], ';');
                    }
                });

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{q: string, log: string, evento: string, desde: ?string, hasta: ?string}  $filtros
     * @return Builder<Activity>
     */
    private function consulta(array $filtros): Builder
    {
        return Activity::query()
            ->when($filtros['q'] !== '', function (Builder $query) use ($filtros): void {
                $termino = "%{$filtros['q']}%";

                $query->where(function (Builder $sub) use ($termino): void {
                    $sub->where('description', 'ilike', $termino)
                        ->orWhere('log_name', 'ilike', $termino)
                        ->orWhereHasMorph('causer', [User::class], function (Builder $persona) use ($termino): void {
                            $persona->where('first_name', 'ilike', $termino)
                                ->orWhere('last_name', 'ilike', $termino)
                                ->orWhere('email', 'ilike', $termino);
                        });
                });
            })
            ->when($filtros['log'] !== '', fn (Builder $query) => $query->where('log_name', $filtros['log']))
            ->when($filtros['evento'] !== '', fn (Builder $query) => $query->where('event', $filtros['evento']))
            ->when($filtros['desde'] !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filtros['hasta']));
    }

    /** @return Collection<int, string> */
    private function logs(): Collection
    {
        return Activity::query()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name');
    }

    /**
     * @return array{q: string, log: string, evento: string, desde: ?string, hasta: ?string}
     */
    private function filtros(Request $request): array
    {
        $desde = $this->fecha($request->query('desde'));
        $hasta = $this->fecha($request->query('hasta'));

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'log' => trim((string) $request->query('log', '')),
            'evento' => array_key_exists((string) $request->query('evento'), self::EVENTS)
                ? (string) $request->query('evento')
                : '',
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    private function fecha(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
