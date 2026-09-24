<?php

declare(strict_types=1);

namespace App\Core\Auth\Services;

use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Enums\VerificationResult;
use App\Core\Auth\Exceptions\TwoFactorLockedException;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\ProtectedAccounts;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A REGRA da verificação em duas etapas no login, em um lugar só.
 *
 * O QUE É: opcional, por conta. Quem liga passa a entrar em dois passos —
 * senha certa → código de 6 dígitos por e-mail → sessão. O canal é o e-mail
 * que o kit já usa para ação sensível (motor comum: VerificationCodes, com a
 * finalidade própria VerificationPurpose::LoginChallenge).
 *
 * UMA PREFERÊNCIA SÓ, DOIS LOGINS. A coluna `two_factor_enabled_at` vale
 * para o login do painel do cliente (/login) e para o do /admin (Filament).
 * Não são preferências separadas de propósito: os dois autenticam o MESMO
 * guard de sessão (`web`), então quem entra por um já está dentro do outro —
 * ligar o segundo fator só no /admin deixaria o /login como porta dos fundos.
 *
 * LIGAR E DESLIGAR SÃO AÇÕES SENSÍVEIS: exigem o token de ação sensível
 * (senha de transação + código por e-mail — SensitiveActionService). Quem
 * roubou a sessão ou a senha de login não liga (nem desliga) o segundo fator
 * de ninguém. O token é conferido AQUI, e não só na tela: nenhum caminho
 * troca a preferência sem ele.
 *
 * O QUE NÃO MUDA A PREFERÊNCIA: troca de senha no perfil e redefinição por
 * e-mail. O segundo fator existe justamente para o dia em que a senha vazou —
 * se trocar a senha o desligasse, quem descobriu a senha também desligaria.
 *
 * LIMITES DE TENTATIVA, em três camadas:
 *   1. por CÓDIGO (AUTH_VERIFICATION_CODE_MAX_ATTEMPTS): o código morre;
 *   2. por CONTA (AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_ACCOUNT): sem ele, bastaria
 *      pedir código novo a cada N erros e continuar tentando;
 *   3. por IP (AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_IP): a mesma origem tentando
 *      várias contas.
 * As duas últimas somam dentro da janela AUTH_TWO_FACTOR_LOCKOUT_MINUTES.
 *
 * CONTAS PROTEGIDAS (App\Core\Auth\Contracts\AccountProtection): enquanto
 * a proteção vale, a preferência delas é imutável. Aqui a recusa sai antes,
 * com mensagem clara, em vez da exceção do model.
 */
final class TwoFactorLogin
{
    public function __construct(
        private readonly VerificationCodes $codes,
        private readonly SensitiveActionService $sensitiveActions,
    ) {}

    /**
     * A opção existe nesta instalação? (AUTH_TWO_FACTOR_ENABLED)
     */
    public static function available(): bool
    {
        return (bool) config('auth.two_factor.enabled', true);
    }

    /**
     * O login desta conta passa pelo segundo fator?
     */
    public function enabledFor(User $user): bool
    {
        return self::available() && $user->two_factor_enabled_at !== null;
    }

    /**
     * Motivo (traduzido) pelo qual a conta NÃO pode ligar/desligar agora, ou
     * null quando pode. A tela usa para explicar; enable()/disable() usam
     * para recusar.
     */
    public function blockedReason(User $user): ?string
    {
        if (! self::available()) {
            return __('auth.two_factor.unavailable');
        }

        if (ProtectedAccounts::protects($user)) {
            return __('auth.two_factor.demo_blocked');
        }

        if (! $user->hasTransactionPassword()) {
            return __('auth.two_factor.requires_transaction_password');
        }

        return null;
    }

    /**
     * Liga o segundo fator. Exige o token de ação sensível (uso único).
     *
     * @throws ValidationException
     */
    public function enable(User $user, #[\SensitiveParameter] string $sensitiveToken): void
    {
        $this->authorizeChange($user, $sensitiveToken);

        $user->forceFill(['two_factor_enabled_at' => now()])->save();
    }

    /**
     * Desliga o segundo fator. Exige o token de ação sensível (uso único).
     *
     * @throws ValidationException
     */
    public function disable(User $user, #[\SensitiveParameter] string $sensitiveToken): void
    {
        $this->authorizeChange($user, $sensitiveToken);

        $user->forceFill(['two_factor_enabled_at' => null])->save();

        $this->codes->invalidate($user, VerificationPurpose::LoginChallenge);
    }

    /**
     * Envia o código do login, respeitando o intervalo de reenvio.
     *
     * Devolve os segundos que faltam quando ainda não pode enviar (0 = enviou
     * agora). Dentro do intervalo, o código enviado há pouco continua valendo.
     */
    public function sendCode(User $user): int
    {
        $remaining = $this->codes->cooldownRemaining($user, VerificationPurpose::LoginChallenge);

        if ($remaining > 0) {
            return $remaining;
        }

        $this->codes->issue($user, VerificationPurpose::LoginChallenge);

        return 0;
    }

    /**
     * Segundos que faltam para poder pedir outro código.
     */
    public function resendCooldown(User $user): int
    {
        return $this->codes->cooldownRemaining($user, VerificationPurpose::LoginChallenge);
    }

    /**
     * Confere o código do login.
     *
     * @throws TwoFactorLockedException Conta ou IP com códigos errados demais —
     *                                  inclusive quando ESTA tentativa atinge o limite.
     */
    public function verify(User $user, #[\SensitiveParameter] string $code, ?string $ip): VerificationResult
    {
        $this->ensureNotLocked($user, $ip);

        $result = $this->codes->verify($user, VerificationPurpose::LoginChallenge, $code);

        if ($result === VerificationResult::Valid) {
            RateLimiter::clear($this->accountKey($user));

            return $result;
        }

        if ($result === VerificationResult::Invalid) {
            RateLimiter::hit($this->accountKey($user), $this->lockoutSeconds());

            if ($ip !== null) {
                RateLimiter::hit($this->ipKey($ip), $this->lockoutSeconds());
            }

            // Acabou de atingir o limite: o código em curso morre junto (o
            // bloqueio não pode ser contornado esperando só o contador) e quem
            // chama já recebe o bloqueio, não mais um "código incorreto".
            $locked = $this->lockedSeconds($user, $ip);

            if ($locked > 0) {
                $this->codes->invalidate($user, VerificationPurpose::LoginChallenge);

                throw new TwoFactorLockedException($locked);
            }
        }

        return $result;
    }

    /**
     * @throws TwoFactorLockedException
     */
    public function ensureNotLocked(User $user, ?string $ip): void
    {
        $seconds = $this->lockedSeconds($user, $ip);

        if ($seconds > 0) {
            throw new TwoFactorLockedException($seconds);
        }
    }

    /**
     * Segundos de bloqueio que faltam para a conta ou o IP (0 = livre).
     */
    public function lockedSeconds(User $user, ?string $ip): int
    {
        $seconds = 0;

        if (RateLimiter::tooManyAttempts($this->accountKey($user), $this->maxAttemptsPerAccount())) {
            $seconds = RateLimiter::availableIn($this->accountKey($user));
        }

        if ($ip !== null && RateLimiter::tooManyAttempts($this->ipKey($ip), $this->maxAttemptsPerIp())) {
            $seconds = max($seconds, RateLimiter::availableIn($this->ipKey($ip)));
        }

        return $seconds > 0 ? max(1, $seconds) : 0;
    }

    /**
     * A pessoa desistiu (ou o estado intermediário venceu): o código em curso
     * deixa de valer.
     */
    public function cancel(User $user): void
    {
        $this->codes->invalidate($user, VerificationPurpose::LoginChallenge);
    }

    public function challengeTtlMinutes(): int
    {
        return max(1, (int) config('auth.two_factor.challenge_ttl_minutes', 10));
    }

    public function codeTtlMinutes(): int
    {
        return $this->codes->ttlMinutes();
    }

    /**
     * @throws ValidationException
     */
    private function authorizeChange(User $user, string $sensitiveToken): void
    {
        $reason = $this->blockedReason($user);

        if ($reason !== null) {
            throw ValidationException::withMessages(['two_factor' => $reason]);
        }

        if (! $this->sensitiveActions->validateToken($user, $sensitiveToken)) {
            throw ValidationException::withMessages([
                'two_factor' => __('auth.sensitive_action.invalid_token'),
            ]);
        }
    }

    private function accountKey(User $user): string
    {
        return 'two-factor-login|account|'.$user->getAuthIdentifier();
    }

    private function ipKey(string $ip): string
    {
        return 'two-factor-login|ip|'.$ip;
    }

    private function maxAttemptsPerAccount(): int
    {
        return max(1, (int) config('auth.two_factor.max_attempts_per_account', 10));
    }

    private function maxAttemptsPerIp(): int
    {
        return max(1, (int) config('auth.two_factor.max_attempts_per_ip', 30));
    }

    private function lockoutSeconds(): int
    {
        return max(1, (int) config('auth.two_factor.lockout_minutes', 15)) * 60;
    }
}
