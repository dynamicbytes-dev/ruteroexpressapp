<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Support\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Configuración operativa: tarifas por ciudad, comisión, recargo nocturno,
 * radios de asignación, topes de billetera y datos bancarios para recargas.
 *
 * Las tarifas son independientes por zona. El resto de la configuración es
 * global porque aplica al funcionamiento de toda la plataforma.
 */
class SettingsController extends Controller
{
    public const GROUPS = [
        'tarifas' => [
            'label' => 'Tarifas y comisión',
            'help' => 'Lo que se cobra por viaje en la ciudad seleccionada y lo que recibe el conductor.',
        ],
        'asignacion' => [
            'label' => 'Asignación de viajes',
            'help' => 'Radios, tiempos de respuesta y penalización por rechazos.',
        ],
        'billetera' => [
            'label' => 'Billetera',
            'help' => 'Límites de saldo y retiros.',
        ],
        'banco' => [
            'label' => 'Datos para transferencias',
            'help' => 'Cuenta nacional que se muestra en la app al recargar.',
        ],
    ];

    public function edit(Request $request): View
    {
        $ciudades = City::query()->active()->orderBy('name')->get();

        $ciudad = $ciudades->firstWhere('id', (int) $request->query('ciudad'))
            ?? $ciudades->first();

        $grupos = [];

        foreach (self::GROUPS as $clave => $meta) {
            $campos = [];

            foreach (Configuracion::defaults() as $key => $default) {
                if ($default['group'] !== $clave) {
                    continue;
                }

                $porCiudad = Configuracion::esPorCiudad($key) && $ciudad !== null;
                $valor = $porCiudad
                    ? Configuracion::get($key, null, $ciudad->getKey())
                    : Configuracion::get($key);

                $campos[] = [
                    'key' => $key,
                    'label' => $default['label'],
                    'description' => $default['description'] ?? null,
                    'type' => $default['type'],
                    'por_ciudad' => $porCiudad,
                    'value' => $default['type'] === 'json' && is_array($valor)
                        ? implode(', ', $valor)
                        : (string) $valor,
                ];
            }

            $grupos[$clave] = array_merge($meta, ['campos' => $campos]);
        }

        return view('panel.configuracion.edit', [
            'grupos' => $grupos,
            'ciudades' => $ciudades,
            'ciudad' => $ciudad,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ciudad = City::query()
            ->active()
            ->find((int) $request->input('city_id'));

        $datos = $request->validate($this->reglas($ciudad !== null), messages: $this->mensajes());

        foreach (Configuracion::defaults() as $key => $default) {
            if (! array_key_exists($key, $datos)) {
                continue;
            }

            $valor = $datos[$key];

            if ($default['type'] === 'json') {
                $valor = array_values(array_map(
                    fn (string $parte): int => (int) trim($parte),
                    explode(',', (string) $valor),
                ));
            }

            $porCiudad = Configuracion::esPorCiudad($key) && $ciudad !== null;

            Configuracion::put($key, $valor, $porCiudad ? $ciudad->getKey() : null);
        }

        return redirect()
            ->route('panel.configuracion.edit', $ciudad === null ? [] : ['ciudad' => $ciudad->getKey()])
            ->with(
                'status',
                $ciudad === null
                    ? 'Configuración guardada.'
                    : "Configuración guardada. Las tarifas de {$ciudad->name} aplican a los próximos viajes de esa zona.",
            );
    }

    /** @return array<string, array<int, string>> */
    private function reglas(bool $hayCiudad): array
    {
        $reglas = [
            'city_id' => [$hayCiudad ? 'required' : 'nullable', 'integer', 'exists:cities,id'],
        ];

        foreach (Configuracion::defaults() as $key => $default) {
            $reglas[$key] = match ($default['type']) {
                'decimal' => ['required', 'numeric'],
                'integer' => ['required', 'integer', 'min:1', 'max:100000'],
                'json' => ['required', 'string', 'regex:/^\s*\d+(\s*,\s*\d+)+\s*$/'],
                default => $default['group'] === 'banco'
                    ? ['required', 'string', 'max:500']
                    : ['required', 'string', 'max:100'],
            };
        }

        // Reglas específicas para que los valores tengan sentido operativo.
        $reglas['comision_porcentaje'] = ['required', 'numeric', 'min:0', 'max:100'];
        $reglas['recargo_nocturno_porcentaje'] = ['required', 'numeric', 'min:0', 'max:200'];
        $reglas['tarifa_base'] = ['required', 'numeric', 'min:0', 'max:10000'];
        $reglas['precio_km'] = ['required', 'numeric', 'min:0', 'max:10000'];
        $reglas['precio_minuto'] = ['required', 'numeric', 'min:0', 'max:10000'];
        $reglas['tarifa_minima'] = ['required', 'numeric', 'min:0', 'max:10000'];
        $reglas['recargo_carro_usd'] = ['required', 'numeric', 'min:0', 'max:10000'];
        $reglas['velocidad_promedio_moto_kmh'] = ['required', 'integer', 'min:1', 'max:120'];
        $reglas['velocidad_promedio_carro_kmh'] = ['required', 'integer', 'min:1', 'max:120'];
        $reglas['dinamica_min'] = ['required', 'numeric', 'min:1', 'max:10'];
        $reglas['dinamica_max'] = ['required', 'numeric', 'gte:dinamica_min', 'max:10'];
        $reglas['dinamica_actual'] = ['required', 'numeric', 'gte:dinamica_min', 'lte:dinamica_max'];
        $reglas['tope_saldo_negativo'] = ['required', 'numeric', 'max:0', 'min:-1000'];
        $reglas['retiro_minimo_usd'] = ['required', 'numeric', 'min:0.01', 'max:1000'];
        $reglas['nocturno_desde'] = ['required', 'date_format:H:i'];
        $reglas['nocturno_hasta'] = ['required', 'date_format:H:i'];
        $reglas['radio_inicial_km'] = ['required', 'integer', 'min:1', 'max:100'];
        $reglas['radio_favorito_km'] = ['required', 'integer', 'min:0', 'max:100'];
        $reglas['tiempo_respuesta_segundos'] = ['required', 'integer', 'min:20', 'max:120'];
        $reglas['rechazos_para_bloqueo'] = ['required', 'integer', 'min:1', 'max:20'];
        $reglas['ventana_rechazos_minutos'] = ['required', 'integer', 'min:1', 'max:1440'];
        $reglas['duracion_bloqueo_minutos'] = ['required', 'integer', 'min:1', 'max:1440'];

        return $reglas;
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        return [
            'required' => 'Completa todos los campos de la configuración.',
            'numeric' => 'Este valor debe ser un número.',
            'integer' => 'Este valor debe ser un número entero.',
            'city_id.required' => 'Elige la ciudad cuyas tarifas vas a guardar.',
            'city_id.exists' => 'La ciudad seleccionada ya no está disponible.',
            'dinamica_max.gte' => 'El multiplicador máximo no puede ser menor que el mínimo.',
            'dinamica_actual.gte' => 'El multiplicador actual no puede ser menor que el mínimo.',
            'dinamica_actual.lte' => 'El multiplicador actual no puede ser mayor que el máximo.',
            'tope_saldo_negativo.max' => 'El tope negativo debe ser cero o un número negativo.',
            'nocturno_desde.date_format' => 'Usa el formato HH:MM, por ejemplo 19:00.',
            'nocturno_hasta.date_format' => 'Usa el formato HH:MM, por ejemplo 06:00.',
            'radios_km.regex' => 'Escribe los radios separados por coma. Necesitas al menos dos, por ejemplo 3, 5, 8.',
        ];
    }
}
