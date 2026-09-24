<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Exceptions\TwoFactorLockedException;
use App\Core\Auth\Http\Requests\LoginRequest;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Core\Auth\Support\PendingTwoFactorLogin;
use App\Core\Auth\Support\PostLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sessão web (login/logout).
 *
 * - Bloqueio por tentativas: throttle + contador via RateLimiter, chaveado
 *   por e-mail + IP, com decay configurável (config auth.login).
 * - Deny-by-default: somente contas ativas autenticam.
 * - Anti-enumeração: a mesma mensagem para e-mail inexistente ou senha errada.
 * - Login regenera o ID da sessão (fixation); logout invalida e renova o
 *   token CSRF.
 * - Verificação em duas etapas (opcional, por conta — TwoFactorLogin): com
 *   ela ligada, a senha certa NÃO autentica; abre o estado intermediário
 *   (PendingTwoFactorLogin), envia o código por e-mail e leva à tela do
 *   código (TwoFactorChallengeController), que é quem conclui o login.
 */
final class AuthenticatedSessionController
{
    public function __construct(
        private readonly TwoFactorLogin $twoFactor,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        /** @var array{email: string, password: string} $credentials */
        $credentials = $request->only('email', 'password');

        $user = User::query()->where('email', $credentials['email'])->first();

        // Credenciais válidas mas conta inativa: mensagem específica (o
        // atacante já saberia as credenciais — não há vazamento adicional).
        if ($user !== null && ! $user->isActive() && Auth::validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.account_inactive'),
            ]);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        /** @var User $user */
        return $this->completeLogin($request, $user);
    }

    /**
     * Conclui o login: segundo fator, quando a conta tem, ou sessão
     * autenticada direto.
     *
     * @throws ValidationException
     */
    private function completeLogin(LoginRequest $request, User $user): RedirectResponse
    {
        if ($this->twoFactor->enabledFor($user)) {
            return $this->startTwoFactorChallenge($request, $user);
        }

        Auth::login($user, $request->boolean('remember'));

        // Prevenção de session fixation.
        $request->session()->regenerate();

        return PostLoginRedirect::to($request);
    }

    /**
     * Senha certa numa conta com segundo fator: nada de sessão autenticada
     * ainda. Só chega aqui quem acertou a senha de uma conta ativa — a
     * resposta a senha errada e a e-mail inexistente continua a mesma de
     * sempre (anti-enumeração).
     *
     * @throws ValidationException Conta ou IP bloqueados por códigos errados demais.
     */
    private function startTwoFactorChallenge(LoginRequest $request, User $user): RedirectResponse
    {
        try {
            $this->twoFactor->ensureNotLocked($user, $request->ip());
        } catch (TwoFactorLockedException $exception) {
            throw ValidationException::withMessages(['email' => $exception->userMessage()]);
        }

        PendingTwoFactorLogin::start($request, $user, $request->boolean('remember'), $this->twoFactor->challengeTtlMinutes());

        // O estado intermediário também ganha ID de sessão novo (fixation).
        $request->session()->regenerate();

        $this->twoFactor->sendCode($user);

        return redirect()->route('two-factor.challenge');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }

    /**
     * Bloqueio após N tentativas (config auth.login.max_attempts).
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $maxAttempts = (int) config('auth.login.max_attempts', 5);

        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Chave do limiter: e-mail (normalizado) + IP — um atacante distribuído
     * não pode testar senhas da mesma conta trocando de IP à vontade.
     */
    private function throttleKey(LoginRequest $request): string
    {
        return 'login|'.Str::transliterate(Str::lower($request->string('email')->toString())).'|'.$request->ip();
    }

    private function decaySeconds(): int
    {
        return (int) config('auth.login.lockout_minutes', 15) * 60;
    }
}
