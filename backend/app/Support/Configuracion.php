<?php

namespace App\Support;

use App\Models\City;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Valores configurables desde el panel del auditor.
 *
 * Todo lo que el negocio puede ajustar sin recompilar la app vive aquí: tarifas,
 * radios de asignación, tiempos, topes de saldo y datos bancarios. La app móvil
 * nunca calcula precios: recibe el resultado del backend.
 *
 * Las tarifas se pueden definir por ciudad. Si una ciudad no tiene un valor
 * propio se usa el global, así una zona nueva arranca con los valores base.
 */
class Configuracion
{
    /** @var array<string, Collection<string, Setting>> */
    private static array $valores = [];

    /**
     * Valores semilla y metadatos del formulario.
     *
     * @return array<string, array{value: mixed, type: string, group: string, label: string, description?: string}>
     */
    public static function defaults(): array
    {
        return [
            // Tarifas
            'tarifa_base' => [
                'value' => 1.00,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Tarifa base (USD)',
                'description' => 'Monto fijo que se cobra al iniciar cualquier viaje.',
            ],
            'precio_km' => [
                'value' => 0.35,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Precio por kilómetro (USD)',
            ],
            'precio_minuto' => [
                'value' => 0.05,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Precio por minuto (USD)',
            ],
            'tarifa_minima' => [
                'value' => 1.50,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Mínimo por viaje (USD)',
            ],
            'recargo_carro_usd' => [
                'value' => 1.00,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Recargo por viaje en carro (USD)',
                'description' => 'Se suma al total del viaje cuando el pasajero elige carro. La moto no lo paga.',
            ],
            'comision_porcentaje' => [
                'value' => 10,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Comisión de GAZ (%)',
                'description' => 'Porcentaje que retiene la app. El conductor recibe el resto.',
            ],
            'recargo_nocturno_porcentaje' => [
                'value' => 20,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Recargo nocturno (%)',
            ],
            'nocturno_desde' => [
                'value' => '19:00',
                'type' => 'string',
                'group' => 'tarifas',
                'label' => 'Empieza la franja nocturna',
            ],
            'nocturno_hasta' => [
                'value' => '06:00',
                'type' => 'string',
                'group' => 'tarifas',
                'label' => 'Termina la franja nocturna',
            ],
            'dinamica_min' => [
                'value' => 1.0,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Multiplicador dinámico mínimo',
            ],
            'dinamica_max' => [
                'value' => 1.8,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Multiplicador dinámico máximo',
            ],
            'dinamica_actual' => [
                'value' => 1.0,
                'type' => 'decimal',
                'group' => 'tarifas',
                'label' => 'Multiplicador dinámico actual',
                'description' => 'Se aplica a los viajes nuevos. Debe estar entre el mínimo y el máximo.',
            ],

            // Asignación de viajes
            'radio_inicial_km' => [
                'value' => 3,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Radio inicial (km)',
            ],
            'radios_km' => [
                'value' => [3, 5, 8],
                'type' => 'json',
                'group' => 'asignacion',
                'label' => 'Radios en cascada (km)',
                'description' => 'Separados por coma. Si nadie acepta, se pasa al siguiente radio.',
            ],
            'radio_favorito_km' => [
                'value' => 8,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Radio del conductor favorito (km)',
                'description' => 'Antes de la cascada normal se ofrece al conductor favorito del pasajero que esté dentro de este radio. Usa 0 para desactivar la prioridad.',
            ],
            'tiempo_respuesta_segundos' => [
                'value' => 20,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Tiempo para aceptar (segundos)',
                'description' => 'Tiempo mínimo de espera para que el conductor acepte la oferta antes de considerarla vencida (mínimo 20 segundos).',
            ],
            'velocidad_promedio_moto_kmh' => [
                'value' => 25,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Velocidad promedio de moto (km/h)',
                'description' => 'Se usa para estimar la duración cuando no hay un servicio de rutas disponible.',
            ],
            'velocidad_promedio_carro_kmh' => [
                'value' => 30,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Velocidad promedio de carro (km/h)',
                'description' => 'Se usa para estimar la duración cuando no hay un servicio de rutas disponible.',
            ],
            'rechazos_para_bloqueo' => [
                'value' => 3,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Rechazos que generan bloqueo',
            ],
            'ventana_rechazos_minutos' => [
                'value' => 60,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Ventana de conteo (minutos)',
            ],
            'duracion_bloqueo_minutos' => [
                'value' => 60,
                'type' => 'integer',
                'group' => 'asignacion',
                'label' => 'Duración del bloqueo (minutos)',
            ],

            // Billetera
            'tope_saldo_negativo' => [
                'value' => -5.00,
                'type' => 'decimal',
                'group' => 'billetera',
                'label' => 'Tope de saldo negativo (USD)',
                'description' => 'El conductor puede quedar en negativo hasta este monto por comisiones en efectivo.',
            ],
            'retiro_minimo_usd' => [
                'value' => 1.00,
                'type' => 'decimal',
                'group' => 'billetera',
                'label' => 'Retiro mínimo (USD)',
            ],

            // Datos para transferencias nacionales
            'banco_nombre' => [
                'value' => 'Banesco',
                'type' => 'string',
                'group' => 'banco',
                'label' => 'Banco receptor',
            ],
            'banco_cuenta' => [
                'value' => '0134 0000 0000 0000 0000',
                'type' => 'string',
                'group' => 'banco',
                'label' => 'Número de cuenta',
            ],
            'banco_titular' => [
                'value' => 'GAZ C.A.',
                'type' => 'string',
                'group' => 'banco',
                'label' => 'Titular de la cuenta',
            ],
            'banco_rif' => [
                'value' => 'J-000000000',
                'type' => 'string',
                'group' => 'banco',
                'label' => 'RIF',
            ],
            'banco_instrucciones' => [
                'value' => 'Haz la transferencia o pago móvil y sube el comprobante. La recarga se acredita cuando el equipo la verifica.',
                'type' => 'string',
                'group' => 'banco',
                'label' => 'Instrucciones para la app',
            ],
        ];
    }

    /** Borra la caché en memoria (se llama al guardar una configuración). */
    public static function flush(): void
    {
        self::$valores = [];
    }

    /**
     * Valores globales, o los de una ciudad con los globales como respaldo.
     *
     * @return Collection<string, Setting>
     */
    public static function all(?int $cityId = null): Collection
    {
        $clave = self::cacheKey($cityId);

        if (! array_key_exists($clave, self::$valores)) {
            $globales = Setting::query()
                ->whereNull('city_id')
                ->get()
                ->keyBy('key');

            if ($cityId === null) {
                self::$valores[$clave] = $globales;
            } else {
                $propias = Setting::query()
                    ->where('city_id', $cityId)
                    ->get()
                    ->keyBy('key');

                // Se copia clave por clave: `merge()` de una colección Eloquent
                // reindexa los modelos y se perderían los nombres de las claves.
                foreach ($propias as $key => $setting) {
                    $globales->put($key, $setting);
                }

                self::$valores[$clave] = $globales;
            }
        }

        return self::$valores[$clave];
    }

    public static function get(string $key, mixed $fallback = null, ?int $cityId = null): mixed
    {
        $default = self::defaults()[$key] ?? null;
        $setting = self::all($cityId)->get($key);

        $type = $setting?->type ?? $default['type'] ?? 'string';
        $raw = $setting !== null && $setting->value !== null
            ? $setting->value
            : ($default['value'] ?? null);

        if ($raw === null) {
            return $fallback;
        }

        return match ($type) {
            'decimal' => (float) $raw,
            'integer' => (int) $raw,
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'json' => self::decodeJson($raw, $fallback),
            default => (string) $raw,
        };
    }

    public static function decimal(string $key, float $fallback = 0, ?int $cityId = null): float
    {
        $value = self::get($key, $fallback, $cityId);

        return is_numeric($value) ? (float) $value : $fallback;
    }

    public static function integer(string $key, int $fallback = 0, ?int $cityId = null): int
    {
        $value = self::get($key, $fallback, $cityId);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /** @return array<int, mixed> */
    public static function json(string $key, array $fallback = [], ?int $cityId = null): array
    {
        $value = self::get($key, $fallback, $cityId);

        return is_array($value) ? $value : $fallback;
    }

    /** Guarda un valor global o el de una ciudad y refresca la caché. */
    public static function put(string $key, mixed $value, ?int $cityId = null): Setting
    {
        $default = self::defaults()[$key] ?? [
            'type' => 'string',
            'group' => 'general',
            'label' => $key,
        ];

        $guardado = match ($default['type']) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'boolean' => $value ? '1' : '0',
            default => $value === null ? null : (string) $value,
        };

        $setting = Setting::query()->updateOrCreate(
            ['city_id' => $cityId, 'key' => $key],
            [
                'value' => $guardado,
                'type' => $default['type'],
                'group' => $default['group'],
                'label' => $default['label'],
                'description' => $default['description'] ?? null,
            ],
        );

        self::flush();

        return $setting;
    }

    /** Carga (sin pisar valores ya editados) las claves globales que falten. */
    public static function seedDefaults(): void
    {
        foreach (self::defaults() as $key => $default) {
            Setting::query()->firstOrCreate(
                ['city_id' => null, 'key' => $key],
                self::atributos($default),
            );
        }

        self::flush();
    }

    /**
     * Copia las tarifas globales a una ciudad. Los valores quedan independientes:
     * el auditor puede ajustarlos sin afectar a las demás zonas.
     */
    public static function seedCityDefaults(City $city): void
    {
        foreach (self::defaults() as $key => $default) {
            if (($default['group'] ?? null) !== 'tarifas') {
                continue;
            }

            Setting::query()->firstOrCreate(
                ['city_id' => $city->getKey(), 'key' => $key],
                self::atributos($default),
            );
        }

        self::flush();
    }

    /** ¿La clave se configura por ciudad? Hoy son las tarifas y sus recargos. */
    public static function esPorCiudad(string $key): bool
    {
        return (self::defaults()[$key]['group'] ?? null) === 'tarifas';
    }

    private static function cacheKey(?int $cityId): string
    {
        return $cityId === null ? 'global' : "ciudad:{$cityId}";
    }

    /**
     * @param  array{value: mixed, type: string, group: string, label: string, description?: string}  $default
     * @return array<string, mixed>
     */
    private static function atributos(array $default): array
    {
        return [
            'value' => match ($default['type']) {
                'json' => json_encode($default['value'], JSON_UNESCAPED_UNICODE),
                'boolean' => $default['value'] ? '1' : '0',
                default => (string) $default['value'],
            },
            'type' => $default['type'],
            'group' => $default['group'],
            'label' => $default['label'],
            'description' => $default['description'] ?? null,
        ];
    }

    /** @return array<int, mixed> */
    private static function decodeJson(mixed $raw, mixed $fallback): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw)) {
            return is_array($fallback) ? $fallback : [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Compatibilidad con valores escritos a mano como "3,5,8".
        $partes = array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $v): bool => $v !== ''));

        return $partes !== [] ? $partes : (is_array($fallback) ? $fallback : []);
    }
}
