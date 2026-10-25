<?php

namespace App\Models;

use App\Enums\SupportConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Conversación de soporte de una persona de la app.
 *
 * El panel conserva todas las conversaciones; la API móvil solo entrega la que
 * está abierta. Al cerrarla, la persona deja de verla y puede iniciar otra.
 */
class SupportConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'last_message_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportConversationStatus::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /** Último mensaje, para el resumen del listado del panel. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    /** @param  Builder<SupportConversation>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SupportConversationStatus::Open);
    }

    /** @param  Builder<SupportConversation>  $query */
    public function scopeClosed(Builder $query): void
    {
        $query->where('status', SupportConversationStatus::Closed);
    }

    public function estaAbierta(): bool
    {
        return $this->status === SupportConversationStatus::Open;
    }
}
