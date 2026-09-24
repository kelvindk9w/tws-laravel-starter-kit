<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Responses;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Twstec\Kit\Auth\Contracts\Responses\EmailVerificationResponse as EmailVerificationResponseContract;
use Twstec\Kit\Auth\Contracts\Responses\VerifyEmailResponse as VerifyEmailResponseContract;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;
use Twstec\Kit\Auth\Support\EmailVerificationResult;

/**
 * Padrão: nada pendente vai ao dashboard; o resto volta à tela de aviso,
 * com a explicação (link errado/inválido, espera) ou a confirmação do envio.
 */
final class EmailVerificationResponse implements EmailVerificationResponseContract
{
    public function toResponse(Request $request, EmailVerificationResult $result): Response
    {
        $notice = redirect()->route('verification.notice');

        return match ($result->outcome) {
            EmailVerificationOutcome::NotPending => redirect()->route('dashboard'),
            EmailVerificationOutcome::WrongAccount => $notice->with('verification_error', __('auth.email_verification.wrong_account')),
            EmailVerificationOutcome::InvalidLink => $notice->with('verification_error', __('auth.email_verification.invalid_link')),
            EmailVerificationOutcome::Cooldown => $notice->with('verification_error', __('auth.email_verification.cooldown', ['seconds' => $result->seconds])),
            EmailVerificationOutcome::LinkSent => $notice->with('status', __('auth.email_verification.sent')),
            // Link aceito é do contrato VerifyEmailResponse.
            EmailVerificationOutcome::Verified => app(VerifyEmailResponseContract::class)->toResponse($request),
        };
    }
}
