<?php

namespace App\Models;

use App\Enums\TripOfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'status',
        'distance_to_pickup_km',
        'offered_at',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripOfferStatus::class,
            'distance_to_pickup_km' => 'decimal:2',
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function isPending(): bool
    {
        return $this->status === TripOfferStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
