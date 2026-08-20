<?php

declare(strict_types=1);

namespace App\Core\Auth\Models;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Identifiers\HasPublicCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'transaction_password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicCode, HasUuids, Notifiable;

    /**
     * Prefixo do código público legível (ADR-010): USR-xxxxxx.
     */
    protected const PUBLIC_CODE_PREFIX = 'USR';

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
        ];
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
