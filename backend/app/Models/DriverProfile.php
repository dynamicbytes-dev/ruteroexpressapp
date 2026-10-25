<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VehicleType;
use App\Support\Configuracion;
use Database\Factories\DriverProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DriverProfile extends Model
{
    /** @use HasFactory<DriverProfileFactory> */
    use HasFactory;

    /** La geometría no se expone cruda: se pide con `coordinates()`. */
    protected $hidden = [
        'last_location',
    ];

    protected $fillable = [
        'user_id',
        'city_id',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'vehicle_plate',
        'is_online',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'is_online' => 'boolean',
            'went_online_at' => 'datetime',
            'last_location_at' => 'datetime',
            'rejection_window_started_at' => 'datetime',
            'blocked_until' => 'datetime',
            'rating_average' => 'decimal:2',
            'ratings_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Zona de operación: solo recibe viajes de esta ciudad. */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Pasajeros que marcaron a este conductor como favorito. */
    public function favoritesReceived(): HasMany
    {
        return $this->hasMany(DriverFavorite::class, 'driver_id', 'user_id');
    }

    public function hasRatings(): bool
    {
        return (int) $this->ratings_count > 0;
    }

    /** Promedio listo para pintar: null cuando todavía no tiene calificaciones. */
    public function ratingOrNull(): ?float
    {
        return $this->hasRatings() ? round((float) $this->rating_average, 2) : null;
    }

    /** La placa siempre se guarda en mayúsculas y sin espacios ni guiones. */
    public function setVehiclePlateAttribute(?string $value): void
    {
        $this->attributes['vehicle_plate'] = $value === null
            ? null
            : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    /** ¿Puede recibir alertas de viaje en este momento? */
    public function isAvailable(): bool
    {
        return $this->is_online
            && ($this->blocked_until === null || $this->blocked_until->isPast())
            && $this->user->status === UserStatus::Approved
            && $this->user->role === UserRole::Driver
            && $this->hasFreshLocation()
            && ! $this->hasDebtOverCap();
    }

    /**
     * El conductor puede quedar en negativo por comisiones en efectivo, pero
     * si supera el tope configurado no puede activarse hasta recargar.
     */
    public function hasDebtOverCap(): bool
    {
        $billetera = $this->user->wallet;

        if ($billetera === null) {
            return false;
        }

        $tope = Configuracion::decimal('tope_saldo_negativo', -5.0);

        return (float) $billetera->balance < $tope;
    }

    /** Coordenadas actuales, o null si nunca reportó una posición. */
    public function coordinates(): ?array
    {
        $fila = DB::selectOne(
            'select ST_Y(last_location::geometry) as lat, ST_X(last_location::geometry) as lng
             from driver_profiles where id = ? and last_location is not null',
            [$this->getKey()],
        );

        if ($fila === null) {
            return null;
        }

        return [
            'lat' => (float) $fila->lat,
            'lng' => (float) $fila->lng,
        ];
    }

    /**
     * Una posición sin actualizar no sirve para asignar viajes: si la app
     * dejó de reportar hace más de 2 minutos, el conductor se considera
     * desconectado.
     */
    public function hasFreshLocation(): bool
    {
        return $this->last_location_at !== null
            && $this->last_location_at->greaterThan(now()->subMinutes(2));
    }

    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }

    /** Tipo de vehículo en español, aunque la fila sea anterior a la columna. */
    public function vehicleTypeLabel(): string
    {
        return $this->vehicle_type?->label() ?? VehicleType::Motorcycle->label();
    }

    /** Descripción del vehículo para mostrar en la app y en el panel. */
    public function vehicleDescription(): string
    {
        return "{$this->vehicleTypeLabel()} · {$this->vehicle_brand} {$this->vehicle_model} {$this->vehicle_year} · {$this->vehicle_plate}";
    }

    /** Nombre de la zona, o "Sin zona" si la cuenta es anterior. */
    public function cityName(): string
    {
        return $this->city?->name ?? 'Sin zona';
    }
}
