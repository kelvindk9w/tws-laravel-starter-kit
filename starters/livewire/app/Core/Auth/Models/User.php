<?php

declare(strict_types=1);

namespace App\Core\Auth\Models;

use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Notifications\ResetPasswordNotification;
use App\Core\Auth\Notifications\VerifyEmailNotification;
use App\Core\Auth\Support\ProtectedAccounts;
use App\Core\Uploads\Concerns\HasAvatar;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailBehavior;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Twstec\Kit\Foundation\Identifiers\HasPublicCode;
use Twstec\Kit\Foundation\Identifiers\RoutesByUuid;

/**
 * Usuário da plataforma.
 *
 * Identificadores (3 camadas — anti-enumeração):
 * - `id` interno: NUNCA exposto.
 * - `uuid`: identificador externo seguro (UUID v7 ordered via HasUuids).
 * - `codigo_publico`: legível, `USR-xxxxxx` (HasPublicCode + UNIQUE no banco).
 *
 * Senhas (separadas, ambas com hash Argon2id via config/hashing.php):
 * - `password`: senha de LOGIN.
 * - `transaction_password`: senha de TRANSAÇÃO (ações sensíveis), hash separado.
 *
 * Dados pessoais (criptografia em repouso conforme a classificação do dado):
 * - `name`: cast `encrypted` (AES-256-GCM da APP_KEY) — dado pessoal sensível.
 * - `email`: texto (é a chave de lookup do login; índice UNIQUE exige texto).
 *
 * Verificação de e-mail (MustVerifyEmail): conta nova só opera depois de
 * confirmar o e-mail, quando a exigência está ligada — a regra mora em
 * App\Core\Auth\Support\EmailVerification.
 *
 * Foto de perfil (`avatar()`, `avatarUrl()`): trait HasAvatar, do módulo de
 * Uploads.
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'transaction_password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasAvatar, HasFactory, HasPublicCode, HasUuids, MustVerifyEmailBehavior, Notifiable, RoutesByUuid;

    /**
     * Prefixo do código público legível: USR-xxxxxx.
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
     * CONTAS PROTEGIDAS no nível do model (ponto de extensão
     * App\Core\Auth\Contracts\AccountProtection).
     *
     * A interface do super admin já recusa (UserAdminGuard), mas ela só
     * protege de quem clica. Estes eventos protegem também de quem digita:
     * tinker, comando artisan, job, rotina de importação — tudo que passa por
     * Eloquent. Quais contas, quais campos e com que mensagem é decisão da
     * extensão registrada; sem nenhuma, os eventos não recusam nada.
     */
    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            ProtectedAccounts::guardUpdate($user);
        });

        static::deleting(function (self $user): void {
            ProtectedAccounts::guardDelete($user);
        });

        // `forceDeleting` só existe com SoftDeletes; registrado aqui para
        // que a proteção continue de pé no dia em que o kit adotar exclusão
        // lógica em usuários (o evento é ignorado enquanto não houver).
        static::registerModelEvent('forceDeleting', function (self $user): void {
            ProtectedAccounts::guardDelete($user);
        });
    }

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
            'two_factor_enabled_at' => 'datetime',
            'status' => UserStatus::class,
            'is_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Acesso ao super admin Filament (/admin). Deny-by-default:
     * somente a flag is_admin (concedida pelo comando `user:make-admin`)
     * E conta ativa liberam o painel; os demais recebem 403.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin && $this->isActive();
    }

    /**
     * Conta reservada por uma extensão de proteção (ver
     * App\Core\Auth\Contracts\AccountProtection)?
     *
     * Conta reservada NÃO pode ser bloqueada, editada, excluída nem ter o
     * e-mail verificado por ações do super admin — as ações do UserResource
     * verificam esta guarda e avisam com uma notification. Os eventos do
     * model recusam o mesmo por qualquer outro caminho enquanto a proteção
     * vale. Sem extensão registrada, nenhuma conta é reservada.
     */
    public function isReservedAccount(): bool
    {
        return ProtectedAccounts::reserves($this);
    }

    /**
     * Nome antigo de isReservedAccount(), mantido enquanto o teste das contas
     * demo o usa. O produto chama isReservedAccount(); este sai junto com a
     * demonstração.
     *
     * @deprecated Use isReservedAccount().
     */
    public function isDemo(): bool
    {
        return $this->isReservedAccount();
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
     * Locale preferido do usuário (interface + e-mails).
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
     * E-mail de recuperação de senha no idioma do DESTINATÁRIO (bug de QA #9).
     *
     * A notificação nativa do Laravel usa linhas em inglês do pacote; esta
     * é traduzida (chaves mail.password_reset.*) e enfileirada como os demais.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * E-mail de verificação do cadastro, no layout do kit e no idioma do
     * DESTINATÁRIO (mesmo caminho da recuperação de senha: notificação
     * enfileirada com payload criptografado).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * O e-mail está confirmado?
     *
     * CONTA PROTEGIDA CONTA COMO VERIFICADA (ver ProtectedAccounts). Uma conta
     * protegida contra alteração pode ter e-mail fictício e senha pública —
     * se a verificação dependesse só da coluna, bastaria alguém zerar
     * `email_verified_at` (campo que a proteção deixa livre) para o próximo
     * acesso cair na tela de aviso esperando um e-mail que ninguém recebe.
     * Vale só enquanto a proteção vale; fora dela a conta é uma conta comum.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null || ProtectedAccounts::protects($this);
    }

    /**
     * A conta está ativa? (Login é deny-by-default.)
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
