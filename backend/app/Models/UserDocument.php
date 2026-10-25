<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\UserDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class UserDocument extends Model
{
    /** @use HasFactory<UserDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'file_path',
        'disk',
        'original_name',
        'mime_type',
        'size_bytes',
        'expires_at',
        'expiry_notice_days',
        'uploaded_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'expires_at' => 'date',
            'expiry_notice_days' => 'integer',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->uploaded_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Los documentos nunca se sirven por una URL pública: se genera una URL
     * firmada que caduca en pocos minutos.
     */
    public function temporaryUrl(): string
    {
        return URL::temporarySignedRoute(
            'panel.documents.show',
            now()->addMinutes((int) config('motoruta.documents.signed_url_ttl_minutes', 5)),
            ['document' => $this->getKey()],
        );
    }

    /**
     * URL firmada para que la app muestre la selfie como foto de perfil.
     *
     * Va atada al dueño del documento y dura más que la vista del panel porque
     * la imagen se queda cargada en la sesión de la persona.
     */
    public function avatarUrl(): string
    {
        return URL::temporarySignedRoute(
            'api.perfil.foto',
            now()->addMinutes((int) config('motoruta.documents.avatar_url_ttl_minutes', 60)),
            [
                'document' => $this->getKey(),
                'user' => $this->user_id,
            ],
        );
    }

    public function isExpired(): bool
    {
        // Un documento vence al terminar el día indicado, no al empezar.
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }

    public function daysUntilExpiration(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        // Positivo si todavía faltan días; 0 si vence hoy; negativo si ya venció.
        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }

    /** ¿Está dentro de la ventana de avisos previos? */
    public function isExpiringSoon(int $days = 30): bool
    {
        $restantes = $this->daysUntilExpiration();

        return $restantes !== null && $restantes >= 0 && $restantes <= $days;
    }
}
