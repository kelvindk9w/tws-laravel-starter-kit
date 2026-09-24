<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Responses;

use App\Core\Auth\Contracts\Responses\TwoFactorChallengeResponse as TwoFactorChallengeResponseContract;
use App\Core\Auth\Contracts\Responses\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use App\Core\Auth\Enums\TwoFactorChallengeOutcome;
use App\Core\Auth\Support\TwoFactorChallengeResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padrão: erros do código ficam na tela do código (endereço fixo, nunca o
 * Referer); o resto volta ao login, com a explicação quando há uma.
 */
final class TwoFactorChallengeResponse implements TwoFactorChallengeResponseContract
{
    public function toResponse(Request $request, TwoFactorChallengeResult $result): Response
    {
        return match ($result->outcome) {
            TwoFactorChallengeOutcome::InvalidCode => $this->toChallenge()->withErrors(['code' => __('auth.two_factor.invalid')]),
            TwoFactorChallengeOutcome::ExpiredCode => $this->toChallenge()->withErrors(['code' => __('auth.two_factor.expired')]),
            TwoFactorChallengeOutcome::ResendCooldown => $this->toChallenge()->withErrors(['code' => __('auth.two_factor.resend_cooldown', ['seconds' => $result->seconds])]),
            TwoFactorChallengeOutcome::CodeResent => $this->toChallenge()->with('status', __('auth.two_factor.resent')),
            TwoFactorChallengeOutcome::Abandoned => redirect()->route('login')->withErrors(['email' => (string) $result->message]),
            TwoFactorChallengeOutcome::Cancelled => redirect()->route('login')->with('status', __('auth.two_factor.cancelled')),
            TwoFactorChallengeOutcome::Missing => redirect()->route('login'),
            TwoFactorChallengeOutcome::Expired => redirect()->route('login')->withErrors(['email' => __('auth.two_factor.challenge_expired')]),
            // Login concluído é do contrato TwoFactorLoginResponse; se chegar
            // aqui, a sessão já está autenticada — segue o destino padrão.
            TwoFactorChallengeOutcome::Authenticated => app(TwoFactorLoginResponseContract::class)->toResponse($request),
        };
    }

    private function toChallenge(): RedirectResponse
    {
        return redirect()->route('two-factor.challenge');
    }
}
