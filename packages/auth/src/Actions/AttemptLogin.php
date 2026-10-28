<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\LoginOutcome;
use Twstec\Kit\Auth\Exceptions\TwoFactorLockedException;
use Twstec\Kit\Auth\Services\TwoFactorLogin;
use Twstec\Kit\Auth\Support\PendingTwoFactorLogin;
use Twstec\Kit\Auth\Support\UserModel;
use Twstec\Kit\Foundation\Security\ClientBucket;

/**
 * Regra do login por senha (sessão web) — sem resposta HTTP: quem chama
 * escolhe a resposta pelo resultado (contratos LoginResponse e
 * TwoFactorRequiredResponse).
 *
 * - Bloqueio por tentativas, em duas camadas (config auth.login), na mesma
 *   janela (AUTH_LOGIN_LOCKOUT_MINUTES):
 *     1. por e-mail + CLIENTE (ClientBucket: IPv4 exato, IPv6 pelo prefixo) —
 *        AUTH_LOGIN_MAX_ATTEMPTS, o limite apertado de quem erra a própria
 *        senha;
 *     2. por CONTA (só o e-mail normalizado) — AUTH_LOGIN_MAX_ATTEMPTS_PER_ACCOUNT,
 *        mais alto: o teto de quem testa senhas de UMA conta a partir de
 *        muitos IPs (botnet, rotação de IPv6). Sem ele, cada IP novo ganharia
 *        orçamento novo contra a mesma conta.
 *   Conta inexistente também conta (anti-enumeração: o bloqueio é igual).
 *   O preço da camada 2: quem conhece o e-mail de alguém consegue mantê-lo
 *   bloqueado por janelas seguidas. O bloqueio é temporário e a redefinição
 *   de senha por e-mail zera o contador da conta (ResetPassword) — a dona da
 *   conta tem por onde entrar.
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

        // A senha é conferida UMA vez, em qualquer caso: conferir de novo só
        // para a conta inativa daria a ela um tempo de resposta próprio (dois
        // hashes lentos em vez de um) e denunciaria, pelo relógio, quais
        // e-mails são de contas bloqueadas.
        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey($request, $email), $this->decaySeconds());
            RateLimiter::hit(self::accountThrottleKey($email), $this->decaySeconds());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = UserModel::query()->where('email', $email)->first();

        // Credenciais válidas mas conta inativa: mensagem específica (o
        // atacante já saberia as credenciais — não há vazamento adicional).
        if ($user === null || ! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => __('auth.account_inactive'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $email));
        RateLimiter::clear(self::accountThrottleKey($email));

        /** @var AuthUser $user */
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
     * Chave do limite apertado: e-mail (normalizado) + cliente. O cliente é
     * o ClientBucket (IPv4 exato, IPv6 pelo prefixo): trocar de endereço
     * dentro do próprio /64 não dá orçamento novo. Trocar de IP de verdade
     * dá — quem segura o ataque distribuído é o limite por conta
     * (accountThrottleKey).
     */
    public function throttleKey(Request $request, string $email): string
    {
        return 'login|'.self::normalizeEmail($email).'|'.ClientBucket::for($request);
    }

    /**
     * Chave do limite por CONTA: só o e-mail normalizado, de qualquer origem.
     * Pública e estática para a redefinição de senha zerar o contador.
     */
    public static function accountThrottleKey(string $email): string
    {
        return 'login-account|'.self::normalizeEmail($email);
    }

    private static function normalizeEmail(string $email): string
    {
        return Str::transliterate(Str::lower($email));
    }

    /**
     * Senha certa numa conta com segundo fator: nada de sessão autenticada
     * ainda. Só chega aqui quem acertou a senha de uma conta ativa — a
     * resposta a senha errada e a e-mail inexistente continua a mesma de
     * sempre (anti-enumeração).
     *
     * @throws ValidationException Conta ou IP bloqueados por códigos errados demais.
     */
    private function startTwoFactorChallenge(Request $request, AuthUser $user, bool $remember): void
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
     * Bloqueio após N tentativas do mesmo cliente (auth.login.max_attempts)
     * ou M tentativas contra a mesma conta, de qualquer origem
     * (auth.login.max_attempts_per_account). A mensagem é a mesma nos dois
     * casos — e para conta inexistente.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request, string $email): void
    {
        $seconds = null;

        $clientKey = $this->throttleKey($request, $email);

        if (RateLimiter::tooManyAttempts($clientKey, max(1, (int) config('auth.login.max_attempts', 5)))) {
            $seconds = RateLimiter::availableIn($clientKey);
        }

        $accountKey = self::accountThrottleKey($email);

        if (RateLimiter::tooManyAttempts($accountKey, max(1, (int) config('auth.login.max_attempts_per_account', 20)))) {
            $seconds = max($seconds ?? 0, RateLimiter::availableIn($accountKey));
        }

        if ($seconds === null) {
            return;
        }

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
