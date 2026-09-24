<?php

declare(strict_types=1);

namespace App\Core\Auth\Actions;

use App\Core\Auth\Enums\TwoFactorChallengeOutcome;
use App\Core\Auth\Enums\VerificationResult;
use App\Core\Auth\Exceptions\TwoFactorLockedException;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TwoFactorLogin;
use App\Core\Auth\Support\PendingTwoFactorLogin;
use App\Core\Auth\Support\TwoFactorChallengeResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Segundo passo do login (verificação em duas etapas por e-mail): o código,
 * o reenvio e a desistência.
 *
 * Só existe para quem está no ESTADO INTERMEDIÁRIO (PendingTwoFactorLogin):
 * acertou a senha de uma conta com o segundo fator ligado e ainda não está
 * autenticado. Sem esse estado, ou com ele vencido, o resultado é Missing ou
 * Expired (e o estado vencido é apagado).
 *
 * O login só é concluído com o código certo — e, mesmo então, a conta é
 * conferida de novo (ativa?) antes de a sessão nascer, com ID de sessão novo
 * e "manter conectado" aplicado só agora.
 */
final class CompleteTwoFactorLogin
{
    public function __construct(
        private readonly TwoFactorLogin $twoFactor,
    ) {}

    /**
     * A conta do estado intermediário, ou o resultado de não haver um.
     */
    public function pendingUser(Request $request): User|TwoFactorChallengeResult
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user !== null) {
            return $user;
        }

        // Se havia um estado (venceu, a senha mudou), a pessoa fica sabendo
        // por quê; se nunca houve, só volta ao login.
        if (! PendingTwoFactorLogin::exists($request)) {
            return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::Missing);
        }

        PendingTwoFactorLogin::forget($request);

        return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::Expired);
    }

    /**
     * Confere o código; o certo autentica a sessão.
     */
    public function handle(Request $request, string $code): TwoFactorChallengeResult
    {
        $user = $this->pendingUser($request);

        if ($user instanceof TwoFactorChallengeResult) {
            return $user;
        }

        // Conta bloqueada/pendente no meio do caminho: mesma recusa do login.
        if (! $user->isActive()) {
            return $this->abandon($request, $user, __('auth.account_inactive'));
        }

        try {
            $result = $this->twoFactor->verify($user, $code, $request->ip());
        } catch (TwoFactorLockedException $exception) {
            return $this->abandon($request, $user, $exception->userMessage());
        }

        return match ($result) {
            VerificationResult::Valid => $this->login($request, $user),
            VerificationResult::Invalid => new TwoFactorChallengeResult(TwoFactorChallengeOutcome::InvalidCode),
            VerificationResult::Expired => new TwoFactorChallengeResult(TwoFactorChallengeOutcome::ExpiredCode),
        };
    }

    /**
     * Novo código, respeitando bloqueio e intervalo mínimo.
     */
    public function resend(Request $request): TwoFactorChallengeResult
    {
        $user = $this->pendingUser($request);

        if ($user instanceof TwoFactorChallengeResult) {
            return $user;
        }

        try {
            $this->twoFactor->ensureNotLocked($user, $request->ip());
        } catch (TwoFactorLockedException $exception) {
            return $this->abandon($request, $user, $exception->userMessage());
        }

        $remaining = $this->twoFactor->sendCode($user);

        if ($remaining > 0) {
            return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::ResendCooldown, seconds: $remaining);
        }

        return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::CodeResent);
    }

    /**
     * Desistência: o código em curso morre e o estado intermediário some.
     */
    public function cancel(Request $request): TwoFactorChallengeResult
    {
        $user = PendingTwoFactorLogin::user($request);

        if ($user !== null) {
            $this->twoFactor->cancel($user);
        }

        PendingTwoFactorLogin::forget($request);

        return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::Cancelled);
    }

    /**
     * Código certo: agora sim a sessão é autenticada.
     */
    private function login(Request $request, User $user): TwoFactorChallengeResult
    {
        $remember = PendingTwoFactorLogin::remember($request);

        PendingTwoFactorLogin::forget($request);

        Auth::login($user, $remember);

        // Prevenção de session fixation: a sessão autenticada nasce com ID novo.
        $request->session()->regenerate();

        return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::Authenticated);
    }

    /**
     * Encerra o estado intermediário com um motivo (bloqueio, conta inativa):
     * o código em curso morre.
     */
    private function abandon(Request $request, User $user, string $message): TwoFactorChallengeResult
    {
        $this->twoFactor->cancel($user);
        PendingTwoFactorLogin::forget($request);

        return new TwoFactorChallengeResult(TwoFactorChallengeOutcome::Abandoned, $message);
    }
}
