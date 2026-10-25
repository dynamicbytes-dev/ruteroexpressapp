<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ciudad donde opera GAZ.
 *
 * Cada ciudad es una zona independiente: tiene sus propias tarifas y sus
 * propios conductores. Un viaje solo se ofrece a conductores de su ciudad.
 */
class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'lat',
        'lng',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function driverProfiles(): HasMany
    {
        return $this->hasMany(DriverProfile::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** @param  Builder<City>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Nombre con el estado, para desambiguar en el panel. */
    public function displayName(): string
    {
        return "{$this->name} ({$this->state})";
    }

    /** Ciudad de respaldo para cuentas anteriores a la segmentación por zonas. */
    public static function defaultId(): ?int
    {
        return self::query()
            ->active()
            ->orderByRaw("case when slug = 'puerto-la-cruz' then 0 else 1 end")
            ->orderBy('id')
            ->value('id');
    }
}
