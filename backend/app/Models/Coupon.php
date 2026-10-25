<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Cupón de descuento porcentual creado por el auditor.
 *
 * El descuento se aplica sobre el total del viaje y reduce lo que paga el
 * pasajero. El conductor sigue recibiendo su ganancia completa sobre el
 * total original; la diferencia la asume el sistema (subsidio).
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'discount_percent',
        'min_purchase',
        'max_discount',
        'valid_from',
        'valid_until',
        'max_uses_global',
        'max_uses_per_user',
        'city_id',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'max_uses_global' => 'integer',
            'max_uses_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'discount_percent', 'is_active', 'valid_from', 'valid_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cupones');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /** @param  Builder<Coupon>  $query */
    public function scopeActivos(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Coupon>  $query */
    public function scopeVigentes(Builder $query, ?CarbonInterface $fecha = null): void
    {
        $hoy = $fecha ?? today();

        $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $hoy)
            ->when($this->valid_until !== null || true, fn ($q) => $q
                ->whereNull('valid_until')
                ->orWhereDate('valid_until', '>=', $hoy));
    }

    /**
     * Calcula el descuento en USD sobre un monto, respetando min_purchase
     * y max_discount.
     *
     * @return array{0: float, 1: float} [descuento_aplicado, monto_despueste_descuento]
     */
    public function calcularDescuento(float $monto): array
    {
        if ($this->min_purchase !== null && $monto < (float) $this->min_purchase) {
            return [0.0, $monto];
        }

        $descuento = round($monto * ((float) $this->discount_percent / 100), 2);

        if ($this->max_discount !== null && $descuento > (float) $this->max_discount) {
            $descuento = (float) $this->max_discount;
        }

        $descuento = max(0.0, min($descuento, $monto));

        return [round($descuento, 2), round($monto - $descuento, 2)];
    }

    /**
     * ¿Puede este usuario usar el cupón? Respeta max_uses_global y
     * max_uses_per_user.
     */
    public function puedeUsar(User $usuario): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $hoy = today();

        if ($this->valid_from > $hoy) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until < $hoy) {
            return false;
        }

        if ($this->max_uses_global !== null
            && $this->usages()->count() >= $this->max_uses_global) {
            return false;
        }

        if ($this->max_uses_per_user !== null
            && $this->usages()->where('user_id', $usuario->getKey())->count() >= $this->max_uses_per_user) {
            return false;
        }

        return true;
    }

    /** Marca un uso del cupón por un usuario en un viaje. */
    public function registrarUso(User $usuario, ?Trip $viaje = null): CouponUsage
    {
        return $this->usages()->create([
            'user_id' => $usuario->getKey(),
            'trip_id' => $viaje?->getKey(),
            'used_at' => now(),
        ]);
    }
}
