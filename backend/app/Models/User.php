<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\RestablecerContrasena;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FALaravel\Google2FA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'role',
        'is_owner',
        'first_name',
        'last_name',
        'national_id',
        'email',
        'phone',
        'address',
        'avatar',
        'city_id',
        'password',
        'status',
        'status_reason',
        'submitted_at',
        'terms_accepted_at',
        'terms_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_owner' => 'boolean',
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'blocked_until' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // No se registran contraseñas ni tokens en el historial de auditoría.
        return LogOptions::defaults()
            ->logOnly([
                'role', 'first_name', 'last_name', 'national_id', 'email',
                'phone', 'address', 'city_id', 'status', 'status_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('usuario');
    }

    // -----------------------------------------------------------------
    // Relaciones
    // -----------------------------------------------------------------

    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    /** Ciudad de registro: define la zona de los viajes de un pasajero. */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function verificationReviews(): HasMany
    {
        return $this->hasMany(VerificationReview::class);
    }

    /** Equipos que reciben las notificaciones push de esta cuenta. */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** Conversaciones del chat de soporte, abiertas y en el historial. */
    public function supportConversations(): HasMany
    {
        return $this->hasMany(SupportConversation::class);
    }

    /** Viajes en los que esta persona fue el conductor asignado. */
    public function tripsAsDriver(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    /** Viajes pedidos por esta persona como pasajero. */
    public function tripsAsPassenger(): HasMany
    {
        return $this->hasMany(Trip::class, 'passenger_id');
    }

    /** Alertas de viaje que recibió como conductor. */
    public function tripOffers(): HasMany
    {
        return $this->hasMany(TripOffer::class, 'driver_id');
    }

    /** Calificaciones que recibió como conductor. */
    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(TripRating::class, 'driver_id');
    }

    /** Calificaciones que dejó como pasajero. */
    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(TripRating::class, 'passenger_id');
    }

    /** Conductores que este pasajero marcó como favoritos. */
    public function favoriteDrivers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'driver_favorites',
            'passenger_id',
            'driver_id',
        )->withTimestamps();
    }

    /** Pasajeros que marcaron a esta persona como conductor favorito. */
    public function favoredByPassengers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'driver_favorites',
            'driver_id',
            'passenger_id',
        )->withTimestamps();
    }

    /** Documentos que faltan por subir según el rol. */
    public function missingDocuments(): array
    {
        // Siempre se consulta la base: esta lista decide si una cuenta entra a
        // la cola del auditor, así que no puede depender de una relación que
        // quedó cargada antes de subir un documento.
        $uploaded = $this->documents()->pluck('type')
            ->map(fn (DocumentType|string $type): string => $type instanceof DocumentType
                ? $type->value
                : $type
            );

        return array_values(array_filter(
            $this->role->requiredDocuments(),
            fn (DocumentType $type) => ! $uploaded->contains($type->value)
        ));
    }

    /** ¿Ya subió todo lo que le corresponde? */
    public function hasSubmittedAllDocuments(): bool
    {
        return $this->missingDocuments() === [];
    }

    // -----------------------------------------------------------------
    // Atributos
    // -----------------------------------------------------------------

    public function name(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /** Nombre y apellido con la cédula, para identificar sin ambigüedad en el panel. */
    public function nameWithId(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->name} · V-{$this->national_id}");
    }

    /** URL pública del avatar / foto de perfil del usuario. */
    public function avatarUrl(): ?string
    {
        if (empty($this->avatar)) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        $disk = (string) config('motoruta.avatar_disk', 'public');

        return Storage::disk($disk)->url($this->avatar);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatarUrl();
    }

    /** URL pública o firmada de la foto de perfil o selfie del usuario. */
    public function photoUrl(): ?string
    {
        $avatar = $this->avatarUrl();
        if ($avatar !== null) {
            return $avatar;
        }

        $selfie = $this->relationLoaded('documents')
            ? $this->documents->first(fn ($doc) => $doc->type === DocumentType::Selfie)
            : $this->documents()->where('type', DocumentType::Selfie)->first();

        if ($selfie instanceof UserDocument && str_starts_with((string) $selfie->mime_type, 'image/')) {
            return $selfie->avatarUrl();
        }

        return null;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photoUrl();
    }

    // -----------------------------------------------------------------
    // Consultas
    // -----------------------------------------------------------------

    /** @param  Builder<User>  $query */
    public function scopeRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Cola de trabajo del auditor: cuentas que ya subieron todos sus
     * documentos y esperan la revisión. Las que están a medio registrar
     * (sin `submitted_at`) no ensucian la cola.
     *
     * @param  Builder<User>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->where('status', UserStatus::Pending)->whereNotNull('submitted_at');
    }

    /**
     * Cuentas rechazadas: el auditor encontró un problema y la persona debe
     * corregir. Se separan de la cola porque ya tuvieron una decisión.
     *
     * @param  Builder<User>  $query
     */
    public function scopeNeedsCorrection(Builder $query): void
    {
        $query->where('status', UserStatus::Rejected);
    }

    /** @param  Builder<User>  $query */
    public function scopeSelfRegistrable(Builder $query): void
    {
        $query->whereIn('role', UserRole::selfRegistrable());
    }

    // -----------------------------------------------------------------
    // Reglas de la cuenta
    // -----------------------------------------------------------------

    public function isAuditor(): bool
    {
        return $this->role === UserRole::Auditor;
    }

    /**
     * Auditor principal (clase A): dueño del sistema y único que puede crear
     * otras cuentas de auditor.
     */
    public function isOwner(): bool
    {
        return $this->is_owner === true;
    }

    public function getIsMainAuditorAttribute(): bool
    {
        return $this->isOwner();
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    public function isPassenger(): bool
    {
        return $this->role === UserRole::Passenger;
    }

    /**
     * Solo una cuenta verificada puede pedir viajes, activarse o recargar
     * billetera. Un bloqueo temporal también lo impide.
     */
    public function canOperate(): bool
    {
        if (! $this->status->canOperate()) {
            return false;
        }

        if ($this->blocked_until !== null && $this->blocked_until->isFuture()) {
            return false;
        }

        return true;
    }

    // -----------------------------------------------------------------
    // Verificación
    // -----------------------------------------------------------------

    /** ¿La cuenta está en la cola de revisión del auditor? */
    public function isInReviewQueue(): bool
    {
        return $this->status === UserStatus::Pending && $this->submitted_at !== null;
    }

    /** Documentos que el auditor ya rechazó y la persona debe corregir. */
    public function rejectedDocuments(): HasMany
    {
        return $this->documents()->where('status', DocumentStatus::Rejected);
    }

    /** Documentos que siguen esperando revisión. */
    public function pendingDocuments(): HasMany
    {
        return $this->documents()->where('status', DocumentStatus::Pending);
    }

    /**
     * Recalcula el estado de la cuenta después de subir o corregir documentos.
     *
     * - Si faltan documentos, o alguno quedó rechazado, la cuenta no entra a la
     *   cola (`submitted_at` queda en null).
     * - Cuando están todos y ninguno está rechazado, entra (o vuelve) a la cola.
     *
     * No toca cuentas ya aprobadas, suspendidas o bloqueadas: subir un documento
     * nuevo en esos casos no cambia el estado de la cuenta.
     *
     * @return bool true si la cuenta quedó en la cola de revisión.
     */
    public function actualizarEnvioARevision(): bool
    {
        // La relación pudo quedar cargada antes de subir el documento.
        $this->unsetRelation('documents');

        if (! in_array($this->status, [UserStatus::Pending, UserStatus::Rejected], true)) {
            return false;
        }

        $listo = $this->hasSubmittedAllDocuments()
            && ! $this->documents()->where('status', DocumentStatus::Rejected)->exists();

        if (! $listo) {
            // Sin documentos completos no hay nada que revisar todavía.
            if ($this->submitted_at !== null) {
                $this->forceFill(['submitted_at' => null])->save();
            }

            return false;
        }

        // Se conserva el `submitted_at` original si ya estaba en la cola, para
        // no alterar el orden de llegada cada vez que se sube un documento.
        $this->forceFill([
            'status' => UserStatus::Pending,
            'status_reason' => null,
            'submitted_at' => $this->submitted_at ?? now(),
        ])->save();

        return true;
    }

    /**
     * El auditor solo puede aprobar la cuenta cuando todos los documentos del
     * rol están aprobados. Evita activar a alguien con una foto pendiente.
     */
    public function canBeApproved(): bool
    {
        if ($this->missingDocuments() !== []) {
            return false;
        }

        if ($this->documents()->where('status', '!=', DocumentStatus::Approved)->exists()) {
            return false;
        }

        // Un documento vencido no se puede dar por bueno: primero hay que
        // reenviarlo. La fecha se compara por día, no por hora.
        return ! $this->documents()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today()->toDateString())
            ->exists();
    }

    /** Resumen para la app y el panel: cuántos documentos van por cada estado. */
    public function documentsSummary(): array
    {
        $documents = $this->relationLoaded('documents') ? $this->documents : $this->documents()->get();

        return [
            'required' => count($this->role->requiredDocuments()),
            'uploaded' => $documents->count(),
            'approved' => $documents->where('status', DocumentStatus::Approved)->count(),
            'pending' => $documents->where('status', DocumentStatus::Pending)->count(),
            'rejected' => $documents->where('status', DocumentStatus::Rejected)->count(),
        ];
    }

    // -----------------------------------------------------------------
    // Doble factor (2FA)
    // -----------------------------------------------------------------

    /** ¿La cuenta ya confirmó su aplicación de autenticación? */
    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Genera un secreto nuevo y lo deja pendiente de confirmación. El secreto
     * anterior deja de servir en cuanto se genera este.
     */
    public function generateTwoFactorSecret(): string
    {
        $secreto = $this->google2fa()->generateSecretKey();

        $this->forceFill([
            'two_factor_secret' => $secreto,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secreto;
    }

    /** URL `otpauth://` para registrar la cuenta en Google Authenticator. */
    public function twoFactorQrCodeUrl(): ?string
    {
        if ($this->two_factor_secret === null) {
            return null;
        }

        return $this->google2fa()->getQRCodeUrl(
            config('app.name', 'GAZ'),
            $this->email,
            $this->two_factor_secret,
        );
    }

    /** QR en línea (data URI) listo para pintar en el panel. */
    public function twoFactorQrCodeInline(): ?string
    {
        if ($this->two_factor_secret === null) {
            return null;
        }

        $qr = $this->google2fa()->getQRCodeInline(
            config('app.name', 'GAZ'),
            $this->email,
            $this->two_factor_secret,
        );

        // El backend SVG del paquete devuelve el XML; se convierte a data URI
        // para poder usarlo en un <img> sin inyectar HTML en la vista.
        if (str_contains($qr, '<svg')) {
            return 'data:image/svg+xml;base64,'.base64_encode($qr);
        }

        return $qr;
    }

    /**
     * Valida un código TOTP o, si no coincide, un código de recuperación. El
     * código de recuperación se consume al usarlo.
     */
    public function verifyTwoFactorCode(string $codigo): bool
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';

        if ($codigo === '' || $this->two_factor_secret === null) {
            return false;
        }

        if ($this->google2fa()->verifyKey($this->two_factor_secret, $codigo, 1)) {
            return true;
        }

        return $this->consumeRecoveryCode($codigo);
    }

    /**
     * Confirma la activación y entrega los códigos de recuperación en claro.
     * Es la única vez que se muestran: después solo quedan sus hashes.
     *
     * @return array<int, string>
     */
    public function confirmTwoFactor(): array
    {
        $codigos = $this->generateRecoveryCodes();

        $this->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $codigos;
    }

    /**
     * Crea ocho códigos de recuperación nuevos. Los anteriores dejan de servir.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        $codigos = collect(range(1, 8))
            ->map(fn (): string => mb_strtoupper(Str::random(4).'-'.Str::random(4)))
            ->all();

        $this->forceFill([
            'two_factor_recovery_codes' => array_map(
                fn (string $codigo): string => Hash::make($codigo),
                $codigos,
            ),
        ])->save();

        return $codigos;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /** ¿El código coincide con uno de los códigos de recuperación? */
    private function consumeRecoveryCode(string $codigo): bool
    {
        $codigos = $this->two_factor_recovery_codes ?? [];
        $codigo = mb_strtoupper(trim($codigo));

        foreach ($codigos as $indice => $hash) {
            if (! is_string($hash) || ! Hash::check($codigo, $hash)) {
                continue;
            }

            unset($codigos[$indice]);

            $this->forceFill([
                'two_factor_recovery_codes' => array_values($codigos),
            ])->save();

            return true;
        }

        return false;
    }

    private function google2fa(): Google2FA
    {
        return app('pragmarx.google2fa');
    }

    // -----------------------------------------------------------------
    // Notificaciones
    // -----------------------------------------------------------------

    /**
     * Correo de recuperación con enlace a la web, para que funcione igual desde
     * el navegador (auditor) o desde el teléfono (pasajero y conductor).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }
}
