<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\UserModel;

/**
 * Token de ação sensível: emitido após senha de transação + código
 * de verificação válidos. Autoriza UMA ação sensível (saque, rotação de chave
 * de API, alteração crítica).
 *
 * Invariantes de segurança:
 * - `token_hash`: SOMENTE o hash SHA-256 do token (padrão Sanctum) — o token
 *   em claro é exibido uma única vez na emissão.
 * - `expires_at`: curta duração (config auth.sensitive_action).
 * - `consumed_at`: USO ÚNICO — consumido na primeira validação bem-sucedida.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class SensitiveActionToken extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'consumed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }
}
