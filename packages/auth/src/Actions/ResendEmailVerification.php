<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Auth\Support\EmailVerificationResult;

/**
 * Reenvia o link de verificação de e-mail, respeitando o intervalo mínimo
 * entre envios. Sem nada pendente (exigência desligada ou e-mail já
 * confirmado), não envia nada.
 */
final class ResendEmailVerification
{
    public function handle(AuthUser $user): EmailVerificationResult
    {
        if (! EmailVerification::pendingFor($user)) {
            return new EmailVerificationResult(EmailVerificationOutcome::NotPending);
        }

        $wait = EmailVerification::sendIfAllowed($user);

        if ($wait > 0) {
            return new EmailVerificationResult(EmailVerificationOutcome::Cooldown, $wait);
        }

        return new EmailVerificationResult(EmailVerificationOutcome::LinkSent);
    }
}
