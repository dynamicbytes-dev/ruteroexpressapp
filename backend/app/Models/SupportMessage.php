<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensaje de una conversación de soporte. */
class SupportMessage extends Model
{
    use HasFactory;

    public const AUTOR_USUARIO = 'user';

    public const AUTOR_AUDITOR = 'auditor';

    protected $fillable = [
        'support_conversation_id',
        'sender_id',
        'sender_type',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function esDeUsuario(): bool
    {
        return $this->sender_type === self::AUTOR_USUARIO;
    }

    public function esDeAuditor(): bool
    {
        return $this->sender_type === self::AUTOR_AUDITOR;
    }

    /**
     * Nombre visible para la app. En las respuestas del equipo se muestra el
     * auditor que atendió; si su cuenta fue eliminada, queda el nombre genérico.
     */
    public function autorVisible(): string
    {
        return $this->sender?->name
            ?? ($this->esDeAuditor() ? 'Soporte GAZ' : 'Usuario');
    }
}
