<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Conferência de senha de quem JÁ ESTÁ LOGADO — a senha de login atual (troca
 * de senha no perfil) e a senha de transação (ação sensível, troca da própria
 * senha de transação) — com limite de tentativas erradas POR CONTA.
 *
 * POR QUE AQUI, E NÃO SÓ NA ROTA: o `throttle:sensitive` vale para quem chega
 * por uma rota HTTP que o declara. Uma ação Livewire chega pelo endpoint de
 * atualização do Livewire, sem esse limite; quem roubou uma sessão poderia
 * testar senhas ali sem teto. O limite mora na regra, então vale para
 * qualquer front.
 *
 * - Cada SEGREDO tem o seu contador (senha de login, senha de transação),
 *   por conta, e todos os pontos que conferem o mesmo segredo somam no mesmo
 *   contador (pedir o código da ação sensível e trocar a senha de transação
 *   adivinham a mesma senha).
 * - Esgotadas as tentativas (AUTH_PASSWORD_CONFIRMATION_MAX_ATTEMPTS), a
 *   conferência é recusada — mesmo com a senha certa — até a janela
 *   (AUTH_PASSWORD_CONFIRMATION_LOCKOUT_MINUTES) passar. A senha certa zera o
 *   contador.
 * - A recusa é ValidationException no campo que quem chama indicar.
 */
final class PasswordConfirmation
{
    public const LOGIN_PASSWORD = 'login-password';

    public const TRANSACTION_PASSWORD = 'transaction-password';

    /**
     * A senha de LOGIN atual confere? Erro conta no limite; acerto zera.
     *
     * @throws ValidationException Tentativas esgotadas (no campo `$field`).
     */
    public function confirmLoginPassword(AuthUser $user, #[\SensitiveParameter] string $password, string $field): bool
    {
        return $this->confirm($user, self::LOGIN_PASSWORD, $field, $password, (string) $user->getAuthPassword());
    }

    /**
     * A senha de TRANSAÇÃO confere? Conta sem senha de transação nunca
     * confere (e o erro conta). Erro conta no limite; acerto zera.
     *
     * @throws ValidationException Tentativas esgotadas (no campo `$field`).
     */
    public function confirmTransactionPassword(AuthUser $user, #[\SensitiveParameter] string $password, string $field): bool
    {
        return $this->confirm($user, self::TRANSACTION_PASSWORD, $field, $password, $user->hasTransactionPassword() ? (string) $user->transaction_password : null);
    }

    /**
     * Toda comparação com o segredo que diga "bateu ou não" é uma tentativa,
     * mesmo quando o acerto não é o que quem chama queria (ex.: a senha de
     * transação nova não pode ser igual à de login — a recusa diria que o
     * palpite era a senha de login). Conta uma tentativa e devolve se bateu.
     *
     * @throws ValidationException Tentativas esgotadas (no campo `$field`).
     */
    public function matchesAndCount(AuthUser $user, string $secret, string $field, #[\SensitiveParameter] string $candidate, ?string $hash): bool
    {
        $this->ensureNotLocked($user, $secret, $field);

        RateLimiter::hit($this->key($user, $secret), $this->lockoutSeconds());

        return $hash !== null && Hash::check($candidate, $hash);
    }

    /**
     * @throws ValidationException
     */
    public function ensureNotLocked(AuthUser $user, string $secret, string $field): void
    {
        $key = $this->key($user, $secret);

        if (! RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            return;
        }

        $seconds = max(1, RateLimiter::availableIn($key));

        throw ValidationException::withMessages([
            $field => __('auth.password_confirmation.locked', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function confirm(AuthUser $user, string $secret, string $field, #[\SensitiveParameter] string $candidate, ?string $hash): bool
    {
        $this->ensureNotLocked($user, $secret, $field);

        if ($hash !== null && Hash::check($candidate, $hash)) {
            RateLimiter::clear($this->key($user, $secret));

            return true;
        }

        RateLimiter::hit($this->key($user, $secret), $this->lockoutSeconds());

        return false;
    }

    private function key(AuthUser $user, string $secret): string
    {
        return 'password-confirmation|'.$secret.'|'.$user->getAuthIdentifier();
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('auth.password_confirmation.max_attempts', 5));
    }

    private function lockoutSeconds(): int
    {
        return max(1, (int) config('auth.password_confirmation.lockout_minutes', 15)) * 60;
    }
}
