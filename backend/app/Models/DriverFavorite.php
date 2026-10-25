<?php

namespace App\Models;

use Database\Factories\DriverFavoriteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un conductor que el pasajero marcó como favorito. */
class DriverFavorite extends Model
{
    /** @use HasFactory<DriverFavoriteFactory> */
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'driver_id',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
