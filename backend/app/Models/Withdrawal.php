<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'amount_usd',
        'bank',
        'account_number',
        'account_holder',
        'account_id',
        'status',
        'proof_path',
        'proof_disk',
        'proof_original_name',
        'proof_mime_type',
        'proof_size_bytes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'proof_size_bytes' => 'integer',
            'status' => WithdrawalStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'amount_usd', 'bank', 'account_number',
                'account_holder', 'rejection_reason', 'reviewed_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('retiros');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function proofUrl(): string
    {
        return URL::temporarySignedRoute(
            'panel.retiros.comprobante',
            now()->addMinutes((int) config('motoruta.documents.signed_url_ttl_minutes', 5)),
            ['withdrawal' => $this->getKey()],
        );
    }

    public function proofIsImage(): bool
    {
        return str_starts_with((string) $this->proof_mime_type, 'image/');
    }

    public function setBankAttribute(?string $value): void
    {
        $this->attributes['bank'] = $value === null ? null : trim($value);
    }

    public function setAccountHolderAttribute(?string $value): void
    {
        $this->attributes['account_holder'] = $value === null ? null : trim($value);
    }
}
