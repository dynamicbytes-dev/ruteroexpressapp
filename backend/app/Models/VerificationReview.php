<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\VerificationReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de cada decisión del auditor sobre un documento o
 * sobre la cuenta completa. Nunca se edita ni se borra.
 */
class VerificationReview extends Model
{
    /** @use HasFactory<VerificationReviewFactory> */
    use HasFactory;

    public const SCOPE_DOCUMENT = 'document';

    public const SCOPE_ACCOUNT = 'account';

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_SUSPENDED = 'suspended';

    public const DECISION_REACTIVATED = 'reactivated';

    protected $fillable = [
        'user_id',
        'auditor_id',
        'scope',
        'document_id',
        'document_type',
        'document_path',
        'decision',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(UserDocument::class, 'document_id');
    }

    public function wasApproved(): bool
    {
        return $this->decision === self::DECISION_APPROVED;
    }

    public function wasReactivated(): bool
    {
        return $this->decision === self::DECISION_REACTIVATED;
    }

    public function wasSuspended(): bool
    {
        return $this->decision === self::DECISION_SUSPENDED;
    }

    /** Texto de la decisión para la ficha del usuario. */
    public function decisionLabel(): string
    {
        return match ($this->decision) {
            self::DECISION_APPROVED => 'Aprobó',
            self::DECISION_REJECTED => 'Rechazó',
            self::DECISION_SUSPENDED => 'Suspendió',
            self::DECISION_REACTIVATED => 'Reactivó',
            default => 'Revisó',
        };
    }

    /** Las decisiones favorables se muestran en verde. */
    public function isPositive(): bool
    {
        return in_array($this->decision, [
            self::DECISION_APPROVED,
            self::DECISION_REACTIVATED,
        ], true);
    }

    /**
     * Deja constancia de una decisión del auditor. Nunca se edita ni se borra:
     * es el respaldo de por qué una cuenta quedó aprobada o rechazada.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(array $datos): self
    {
        return self::create($datos);
    }
}
