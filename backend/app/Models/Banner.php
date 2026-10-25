<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'tag',
        'image_path',
        'image_url',
        'background_color',
        'background_color_end',
        'text_color',
        'action_url',
        'action_text',
        'target_role',
        'city_id',
        'is_active',
        'priority',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * URL de la imagen lista para ser consumida por el frontend o panel.
     */
    protected function displayImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->image_path) {
                    return Storage::disk('public')->url($this->image_path);
                }

                return $this->image_url;
            }
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * Banners actualmente vigentes y activos.
     */
    public function scopeActive(Builder $query): Builder
    {
        $ahora = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($ahora) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $ahora);
            })
            ->where(function (Builder $q) use ($ahora) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $ahora);
            });
    }

    /**
     * Filtra según el rol destinatario ('passenger', 'driver', o 'all').
     */
    public function scopeForRole(Builder $query, ?string $role): Builder
    {
        if (! $role) {
            return $query;
        }

        return $query->whereIn('target_role', [$role, 'all']);
    }

    /**
     * Filtra según la ciudad o banners globales (city_id = null).
     */
    public function scopeForCity(Builder $query, ?int $cityId): Builder
    {
        if (! $cityId) {
            return $query->whereNull('city_id');
        }

        return $query->where(function (Builder $q) use ($cityId) {
            $q->whereNull('city_id')->orWhere('city_id', $cityId);
        });
    }
}
