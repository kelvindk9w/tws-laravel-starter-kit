<?php

declare(strict_types=1);

namespace App\Core\Auth\Actions;

use App\Core\Auth\Enums\LoginOutcome;
use App\Core\Auth\Exceptions\TwoFactorLockedException;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Core\Auth\Support\PendingTwoFactorLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Regra do login por senha (sessão web) — sem resposta HTTP: quem chama
 * escolhe a resposta pelo resultado (contratos LoginResponse e
 * TwoFactorRequiredResponse).
 *
 * - Bloqueio por tentativas: contador via RateLimiter, chaveado por e-mail +
 *   IP, com decay configurável (config auth.login).
 * - Deny-by-default: somente contas ativas autenticam.
 * - Anti-enumeração: a mesma mensagem para e-mail inexistente ou senha errada.
 * - Login regenera o ID da sessão (fixation).
 * - Verificação em duas etapas (opcional, por conta — TwoFactorLogin): com
 *   ela ligada, a senha certa NÃO autentica; abre o estado intermediário
 *   (PendingTwoFactorLogin), envia o código por e-mail e devolve
 *   TwoFactorRequired. Quem conclui o login é CompleteTwoFactorLogin.
 *
 * Recusa é sempre ValidationException no campo `email` (o front decide como
 * mostrar: redirect com erro no Blade, 422 em JSON).
 */
final class AttemptLogin
{
    public function __construct(
        private readonly TwoFactorLogin $twoFactor,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Request $request, string $email, string $password, bool $remember): LoginOutcome
    {
        $this->ensureIsNotRateLimited($request, $email);

        $credentials = ['email' => $email, 'password' => $password];

        $user = User::query()->where('email', $email)->first();

        // Credenciais válidas mas conta inativa: mensagem específica (o
        // atacante já saberia as credenciais — não há vazamento adicional).
        if ($user !== null && ! $user->isActive() && Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.account_inactive'),
            ]);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey($request, $email), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $email));

        /** @var User $user */
        if ($this->twoFactor->enabledFor($user)) {
            $this->startTwoFactorChallenge($request, $user, $remember);

            return LoginOutcome::TwoFactorRequired;
        }

        Auth::login($user, $remember);

        // Prevenção de session fixation.
        $request->session()->regenerate();

        return LoginOutcome::Authenticated;
    }

    /**
     * Chave do limiter: e-mail (normalizado) + IP — um atacante distribuído
     * não pode testar senhas da mesma conta trocando de IP à vontade.
     */
    public function throttleKey(Request $request, string $email): string
    {
        return 'login|'.Str::transliterate(Str::lower($email)).'|'.$request->ip();
    }

    /**
     * Senha certa numa conta com segundo fator: nada de sessão autenticada
     * ainda. Só chega aqui quem acertou a senha de uma conta ativa — a
     * resposta a senha errada e a e-mail inexistente continua a mesma de
     * sempre (anti-enumeração).
     *
     * @throws ValidationException Conta ou IP bloqueados por códigos errados demais.
     */
    private function startTwoFactorChallenge(Request $request, User $user, bool $remember): void
    {
        try {
            $this->twoFactor->ensureNotLocked($user, $request->ip());
        } catch (TwoFactorLockedException $exception) {
            throw ValidationException::withMessages(['email' => $exception->userMessage()]);
        }

        PendingTwoFactorLogin::start($request, $user, $remember, $this->twoFactor->challengeTtlMinutes());

        // O estado intermediário também ganha ID de sessão novo (fixation).
        $request->session()->regenerate();

        $this->twoFactor->sendCode($user);
    }

    /**
     * Bloqueio após N tentativas (config auth.login.max_attempts).
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        $maxAttempts = (int) config('auth.login.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $email), $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $email));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    private function decaySeconds(): int
    {
        return (int) config('auth.login.lockout_minutes', 15) * 60;
    }
}
