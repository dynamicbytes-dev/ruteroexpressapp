<?php

namespace App\Models;

use App\Enums\RechargeStatus;
use Database\Factories\RechargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Recharge extends Model
{
    /** @use HasFactory<RechargeFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'amount_bs',
        'exchange_rate',
        'amount_usd',
        'bank',
        'reference',
        'transferred_at',
        'proof_path',
        'proof_disk',
        'proof_original_name',
        'proof_mime_type',
        'proof_size_bytes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_bs' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_usd' => 'decimal:2',
            'transferred_at' => 'date',
            'proof_size_bytes' => 'integer',
            'status' => RechargeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'amount_bs', 'amount_usd', 'exchange_rate',
                'bank', 'reference', 'rejection_reason', 'reviewed_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('recargas');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** URL firmada y temporal para ver el comprobante en el panel. */
    public function proofUrl(): string
    {
        return URL::temporarySignedRoute(
            'panel.recargas.comprobante',
            now()->addMinutes((int) config('motoruta.documents.signed_url_ttl_minutes', 5)),
            ['recharge' => $this->getKey()],
        );
    }

    public function proofIsImage(): bool
    {
        return str_starts_with((string) $this->proof_mime_type, 'image/');
    }

    /**
     * Antifraude: ¿esta referencia bancaria ya se usó en otra recarga?
     *
     * No bloquea automáticamente; el panel se lo advierte al auditor para que
     * decida con el comprobante a la vista.
     */
    public function referenceWasUsedBefore(): bool
    {
        return static::query()
            ->where('id', '!=', $this->getKey())
            ->whereRaw('lower(bank) = ?', [mb_strtolower(trim((string) $this->bank))])
            ->whereRaw('lower(reference) = ?', [mb_strtolower(trim((string) $this->reference))])
            ->exists();
    }

    public function setReferenceAttribute(?string $value): void
    {
        $this->attributes['reference'] = $value === null
            ? null
            : mb_strtoupper(trim($value));
    }

    public function setBankAttribute(?string $value): void
    {
        $this->attributes['bank'] = $value === null ? null : trim($value);
    }
}
