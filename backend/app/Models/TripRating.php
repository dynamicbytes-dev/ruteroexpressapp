<?php

namespace App\Models;

use Database\Factories\TripRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Calificación del pasajero al conductor, una por viaje. */
class TripRating extends Model
{
    /** @use HasFactory<TripRatingFactory> */
    use HasFactory;

    /** Solo se escribe al crear: las calificaciones no se editan. */
    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'passenger_id',
        'driver_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TripRating $calificacion): void {
            $calificacion->created_at ??= now();
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** Texto corto para la app y el panel: «★★★★☆». */
    public function stars(): string
    {
        return str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating);
    }
}
