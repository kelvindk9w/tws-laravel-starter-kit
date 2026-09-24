<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Enums\VerificationResult;
use App\Core\Auth\Exceptions\TwoFactorLockedException;
use App\Core\Auth\Http\Requests\TwoFactorChallengeRequest;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Core\Auth\Support\PendingTwoFactorLogin;
use App\Core\Auth\Support\PostLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Segundo passo do login (verificação em duas etapas por e-mail).
 *
 *   GET  /two-factor-challenge         tela do código (layout do site)
 *   POST /two-factor-challenge         confere o código → sessão autenticada
 *   POST /two-factor-challenge/resend  novo código (intervalo mínimo)
 *   POST /two-factor-challenge/cancel  desiste e volta ao login
 *
 * Só existe para quem está no ESTADO INTERMEDIÁRIO (PendingTwoFactorLogin):
 * acertou a senha de uma conta com o segundo fator ligado e ainda não está
 * autenticado. Sem esse estado, ou com ele vencido, tudo aqui volta ao login.
 *
 * O login só é concluído com o código certo — e, mesmo então, a conta é
 * conferida de novo (ativa?) antes de a sessão nascer, com ID de sessão novo
 * e "manter conectado" aplicado só agora.
 */
final class TwoFactorChallengeController
{
    public function __construct(
        private readonly TwoFactorLogin $twoFactor,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user === null) {
            return $this->backToLogin($request);
        }

        return view('auth.two-factor-challenge', [
            'email' => $user->email,
            'codeTtlMinutes' => $this->twoFactor->codeTtlMinutes(),
        ]);
    }

    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user === null) {
            return $this->backToLogin($request);
        }

        // Conta bloqueada/pendente no meio do caminho: mesma recusa do login.
        if (! $user->isActive()) {
            return $this->abandon($request, $user, __('auth.account_inactive'));
        }

        try {
            $result = $this->twoFactor->verify($user, $request->string('code')->toString(), $request->ip());
        } catch (TwoFactorLockedException $exception) {
            return $this->abandon($request, $user, $exception->userMessage());
        }

        return match ($result) {
            VerificationResult::Valid => $this->login($request, $user),
            VerificationResult::Invalid => $this->toChallenge()->withErrors(['code' => __('auth.two_factor.invalid')]),
            VerificationResult::Expired => $this->toChallenge()->withErrors(['code' => __('auth.two_factor.expired')]),
        };
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user === null) {
            return $this->backToLogin($request);
        }

        try {
            $this->twoFactor->ensureNotLocked($user, $request->ip());
        } catch (TwoFactorLockedException $exception) {
            return $this->abandon($request, $user, $exception->userMessage());
        }

        $remaining = $this->twoFactor->sendCode($user);

        if ($remaining > 0) {
            return $this->toChallenge()->withErrors(['code' => __('auth.two_factor.resend_cooldown', ['seconds' => $remaining])]);
        }

        return $this->toChallenge()->with('status', __('auth.two_factor.resent'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user !== null) {
            $this->twoFactor->cancel($user);
        }

        PendingTwoFactorLogin::forget($request);

        return redirect()->route('login')->with('status', __('auth.two_factor.cancelled'));
    }

    /**
     * De volta à tela do código — endereço fixo, nunca o Referer.
     */
    private function toChallenge(): RedirectResponse
    {
        return redirect()->route('two-factor.challenge');
    }

    /**
     * Código certo: agora sim a sessão é autenticada.
     */
    private function login(Request $request, User $user): RedirectResponse
    {
        $remember = PendingTwoFactorLogin::remember($request);

        PendingTwoFactorLogin::forget($request);

        Auth::login($user, $remember);

        // Prevenção de session fixation: a sessão autenticada nasce com ID novo.
        $request->session()->regenerate();

        return PostLoginRedirect::to($request);
    }

    /**
     * Encerra o estado intermediário com um motivo (bloqueio, conta inativa):
     * o código em curso morre e a pessoa volta ao login com a explicação.
     */
    private function abandon(Request $request, User $user, string $message): RedirectResponse
    {
        $this->twoFactor->cancel($user);
        PendingTwoFactorLogin::forget($request);

        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    /**
     * Sem estado intermediário válido. Se havia um (venceu, a senha mudou),
     * a pessoa fica sabendo por quê; se nunca houve, só volta ao login.
     */
    private function backToLogin(Request $request): RedirectResponse
    {
        if (! PendingTwoFactorLogin::exists($request)) {
            return redirect()->route('login');
        }

        PendingTwoFactorLogin::forget($request);

        return redirect()->route('login')->withErrors(['email' => __('auth.two_factor.challenge_expired')]);
    }
}
