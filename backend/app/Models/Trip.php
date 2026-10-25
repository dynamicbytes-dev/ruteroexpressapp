<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TripStatus;
use App\Enums\VehicleType;
use Carbon\CarbonInterface;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'city_id',
        'coupon_id',
        'promo_type',
        'discount_percent',
        'discount_amount',
        'subsidy_amount',
        'status',
        'payment_method',
        'vehicle_type',
        'origin_address',
        'destination_address',
        'origin_lat',
        'origin_lng',
        'destination_lat',
        'destination_lng',
        'distance_km',
        'duration_minutes',
        'base_fare',
        'price_per_km',
        'price_per_minute',
        'minimum_fare',
        'minimum_applied',
        'dynamic_multiplier',
        'night_surcharge_percent',
        'subtotal',
        'total',
        'commission_percent',
        'commission_amount',
        'driver_earning',
        'exchange_rate',
        'fare_breakdown',
        'wallet_hold_amount',
        'search_radius_index',
        'requested_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'payment_method' => PaymentMethod::class,
            'vehicle_type' => VehicleType::class,
            'origin_lat' => 'decimal:7',
            'origin_lng' => 'decimal:7',
            'destination_lat' => 'decimal:7',
            'destination_lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'integer',
            'base_fare' => 'decimal:2',
            'price_per_km' => 'decimal:2',
            'price_per_minute' => 'decimal:2',
            'minimum_fare' => 'decimal:2',
            'minimum_applied' => 'boolean',
            'dynamic_multiplier' => 'decimal:2',
            'night_surcharge_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'driver_earning' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subsidy_amount' => 'decimal:2',
            'promo_type' => 'string',
            'exchange_rate' => 'decimal:4',
            'fare_breakdown' => 'array',
            'wallet_hold_amount' => 'decimal:2',
            'search_radius_index' => 'integer',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'driver_id', 'city_id', 'total', 'payment_method',
                'cancelled_by', 'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('viajes');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** Zona del viaje: se toma de la ciudad del pasajero al pedirlo. */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(TripOffer::class);
    }

    /** La alerta que está esperando respuesta en este momento. */
    public function currentOffer(): HasOne
    {
        return $this->hasOne(TripOffer::class)
            ->where('status', 'pending')
            ->latestOfMany();
    }

    /** Cupón aplicado al viaje, si corresponde. */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** Calificación del pasajero, si ya la dejó. */
    public function rating(): HasOne
    {
        return $this->hasOne(TripRating::class);
    }

    /** ¿El viaje tuvo un descuento aplicado (cupón o primeros viajes)? */
    public function tieneDescuento(): bool
    {
        return $this->promo_type !== null && (float) $this->discount_amount > 0;
    }

    /** ¿Este pasajero puede calificar el viaje? Solo el suyo, completado y una vez. */
    public function canBeRatedBy(?User $user): bool
    {
        if ($user === null
            || $this->passenger_id !== $user->getKey()
            || $this->status !== TripStatus::Completed) {
            return false;
        }

        $calificacion = $this->relationLoaded('rating')
            ? $this->rating
            : $this->rating()->first();

        return $calificacion === null;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isAssigned(): bool
    {
        return $this->status->isAssigned();
    }

    public function belongsToUser(User $user): bool
    {
        return $this->passenger_id === $user->getKey()
            || $this->driver_id === $user->getKey();
    }

    /** @param  Builder<Trip>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', TripStatus::active());
    }

    /** @param  Builder<Trip>  $query */
    public function scopeForPassenger(Builder $query, User $user): void
    {
        $query->where('passenger_id', $user->getKey());
    }

    /** @param  Builder<Trip>  $query */
    public function scopeForDriver(Builder $query, User $user): void
    {
        $query->where('driver_id', $user->getKey());
    }

    /**
     * Historial desde una fecha.
     *
     * Se usa la misma fecha que pinta la app en cada tarjeta: la finalización,
     * la cancelación o, si el viaje sigue vivo, el momento en que se pidió.
     *
     * @param  Builder<Trip>  $query
     */
    public function scopeHistorySince(Builder $query, CarbonInterface $desde): void
    {
        $query->whereRaw(
            'COALESCE(completed_at, cancelled_at, requested_at) >= ?',
            [$desde->toDateTimeString()],
        );
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            TripStatus::Searching,
            TripStatus::Offered,
            TripStatus::Accepted,
            TripStatus::Arriving,
            TripStatus::Waiting,
        ], true);
    }
}
