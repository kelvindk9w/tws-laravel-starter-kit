<?php

declare(strict_types=1);

namespace App\Core\Auth\Models;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Identifiers\HasPublicCode;
use App\Core\Uploads\Models\Upload;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuário da plataforma (ADR-006/010).
 *
 * Identificadores (3 camadas — ADR-010):
 * - `id` interno: NUNCA exposto.
 * - `uuid`: identificador externo seguro (UUID v7 ordered via HasUuids).
 * - `codigo_publico`: legível, `USR-xxxxxx` (HasPublicCode + UNIQUE no banco).
 *
 * Senhas (ADR-006 — separadas, ambas com hash Argon2id via config/hashing.php):
 * - `password`: senha de LOGIN.
 * - `transaction_password`: senha de TRANSAÇÃO (ações sensíveis), hash separado.
 *
 * Dados pessoais (checklist item 12 — classificação de dados do ADR-006):
 * - `name`: cast `encrypted` (AES-256-GCM da APP_KEY) — dado pessoal sensível.
 * - `email`: texto (é a chave de lookup do login; índice UNIQUE exige texto).
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'transaction_password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicCode, HasUuids, Notifiable;

    /**
     * Prefixo do código público legível (ADR-010): USR-xxxxxx.
     */
    protected const PUBLIC_CODE_PREFIX = 'USR';

    /**
     * Defaults da instância nova (espelham os defaults das migrations) — sem
     * eles, status/is_admin ficam null até o primeiro refresh após o INSERT.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'is_admin' => false,
    ];

    /**
     * Coluna preenchida automaticamente com UUID na criação (HasUuids).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Factory explícita (o model vive fora de App\Models).
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'transaction_password' => 'hashed',
            'transaction_password_set_at' => 'datetime',
            'status' => UserStatus::class,
            'is_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Avatar do perfil (upload validado pela função global da Fase 5).
     *
     * @return BelongsTo<Upload, $this>
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'avatar_upload_id');
    }

    /**
     * URL (assinada) do avatar, ou null quando não definido.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar?->url();
    }

    /**
     * Acesso ao super admin Filament (/admin — ADR-011). Deny-by-default:
     * somente a flag is_admin (concedida pelo comando `user:make-admin`)
     * E conta ativa liberam o painel; os demais recebem 403.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->isActive();
    }

    /**
     * Preferência de notificação efetiva (escolha gravada → default do
     * config/notifications.php). Chaves desconhecidas = false.
     */
    public function notificationPreference(string $key): bool
    {
        $saved = $this->notification_preferences[$key] ?? null;

        if (is_bool($saved)) {
            return $saved;
        }

        return (bool) data_get(config('notifications.preferences'), "{$key}.default", false);
    }

    /**
     * Locale preferido do usuário (interface + e-mails — ADR-007).
     * Sem preferência salva (ou valor fora da whitelist) = padrão da plataforma.
     */
    public function preferredLocale(): string
    {
        $locale = $this->locale;

        if (is_string($locale) && in_array($locale, platform()->availableLocales, true)) {
            return $locale;
        }

        return platform()->locale;
    }

    /**
     * O usuário já definiu a senha de transação?
     */
    public function hasTransactionPassword(): bool
    {
        return $this->transaction_password !== null;
    }

    /**
     * A conta está ativa? (Login é deny-by-default — checklist 13.)
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
