<?php

namespace App\Models;

use App\Support\Configuracion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Setting extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        static::saved(fn () => Configuracion::flush());
        static::deleted(fn () => Configuracion::flush());
    }

    protected $fillable = [
        'city_id',
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value', 'city_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('configuracion');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
