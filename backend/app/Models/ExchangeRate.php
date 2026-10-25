<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'rate',
        'effective_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rate', 'effective_date', 'created_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tasa_cambio');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tasa vigente: la última cargada con fecha de hoy o anterior.
     *
     * Si el auditor todavía no cargó la del día, sigue valiendo la última que
     * haya puesto: nunca se inventa una tasa.
     */
    public static function vigente(?CarbonInterface $fecha = null): ?self
    {
        return static::query()
            ->whereDate('effective_date', '<=', $fecha ?? today())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }
}
