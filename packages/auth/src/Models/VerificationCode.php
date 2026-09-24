<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\VerificationChannel;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Support\UserModel;

/**
 * Código de verificação (2FA por e-mail).
 *
 * Invariantes de segurança:
 * - `code_hash`: SOMENTE o hash do código (Argon2id) — nunca plaintext.
 * - `expires_at`: validade curta obrigatória (config auth.verification).
 * - `attempts`: tentativas de confirmação; ao atingir o máximo, o código é
 *   invalidado (consumed_at preenchido).
 * - `consumed_at`: código usado/invalidado — nunca reutilizável.
 *
 * @property int $id
 * @property int $user_id
 * @property VerificationChannel $channel
 * @property VerificationPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class VerificationCode extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => VerificationChannel::class,
            'purpose' => VerificationPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model&AuthUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::name());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Código ainda utilizável (não expirado, não consumido, tentativas restantes)?
     */
    public function isActive(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }
}
