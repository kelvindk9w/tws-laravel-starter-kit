<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\VerificationChannel;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Enums\VerificationResult;
use Twstec\Kit\Auth\Models\SensitiveActionToken;
use Twstec\Kit\Auth\Models\VerificationCode;

/**
 * Fluxo de confirmação de AÇÃO SENSÍVEL:
 *
 *   1. sendCode()     — valida a SENHA DE TRANSAÇÃO (hash separado) e envia
 *                       um código de 6 dígitos pelo canal configurado (e-mail).
 *   2. confirmCode()  — valida o código (expiração + máx. tentativas + hash)
 *                       e emite um TOKEN DE AÇÃO SENSÍVEL de curta duração.
 *   3. validateToken()— o middleware `sensitive.token` valida (e consome) o
 *                       token antes da operação sensível (saque, rotação de
 *                       chave de API...). USO ÚNICO.
 *
 * Invariantes: código e token NUNCA em plaintext no banco (somente hash),
 * sempre com expiração; reenvio com cooldown; tentativas limitadas. O código
 * em si é do motor comum (VerificationCodes), o mesmo do segundo fator do
 * login — cada fluxo com a sua finalidade.
 */
final class SensitiveActionService
{
    public function __construct(
        private readonly VerificationCodes $codes,
    ) {}

    /**
     * Passo 1: valida a senha de transação e dispara o código de verificação.
     *
     * @throws ValidationException Senha incorreta/não definida ou cooldown ativo.
     */
    public function sendCode(AuthUser $user, #[\SensitiveParameter] string $transactionPassword, ?VerificationChannel $channel = null): VerificationCode
    {
        if (! $user->hasTransactionPassword() || ! Hash::check($transactionPassword, (string) $user->transaction_password)) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.transaction_password.invalid'),
            ]);
        }

        $remaining = $this->codes->cooldownRemaining($user, VerificationPurpose::SensitiveAction, $channel);

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.verification_code.resend_cooldown', ['seconds' => $remaining]),
            ]);
        }

        // Um código novo invalida os ativos anteriores da mesma finalidade.
        return $this->codes->issue($user, VerificationPurpose::SensitiveAction, $channel);
    }

    /**
     * Passo 2: valida o código e emite o token de ação sensível.
     * Retorna o token EM CLARO — exibido uma única vez (no banco, só o hash).
     *
     * @return array{token: string, expires_at: Carbon}
     *
     * @throws ValidationException Código inválido, expirado ou tentativas esgotadas.
     */
    public function confirmCode(AuthUser $user, #[\SensitiveParameter] string $code): array
    {
        return match ($this->codes->verify($user, VerificationPurpose::SensitiveAction, $code)) {
            VerificationResult::Valid => $this->issueToken($user),
            VerificationResult::Invalid => throw ValidationException::withMessages([
                'code' => __('auth.verification_code.invalid'),
            ]),
            VerificationResult::Expired => throw ValidationException::withMessages([
                'code' => __('auth.verification_code.expired'),
            ]),
        };
    }

    /**
     * Passo 3: valida o token de ação sensível (uso único — consome ao validar).
     *
     * O consumo é um UPDATE condicional: duas requisições com o mesmo token
     * ao mesmo tempo não autorizam duas operações.
     */
    public function validateToken(AuthUser $user, #[\SensitiveParameter] string $plainToken): bool
    {
        /** @var SensitiveActionToken|null $token */
        $token = SensitiveActionToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return false;
        }

        return SensitiveActionToken::query()
            ->whereKey($token->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]) === 1;
    }

    /**
     * Segundos restantes de cooldown de reenvio (0 = pode reenviar).
     */
    public function resendCooldownRemaining(AuthUser $user, ?VerificationChannel $channel = null): int
    {
        return $this->codes->cooldownRemaining($user, VerificationPurpose::SensitiveAction, $channel);
    }

    /**
     * Emite o token de ação sensível: aleatório criptograficamente seguro,
     * somente o hash (SHA-256) persistido, expiração curta obrigatória.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    private function issueToken(AuthUser $user): array
    {
        $plainToken = Str::random(64);

        $expiresAt = now()->addMinutes((int) config('auth.sensitive_action.token_ttl_minutes', 10));

        SensitiveActionToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plainToken, 'expires_at' => $expiresAt];
    }
}
