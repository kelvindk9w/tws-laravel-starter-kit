<?php

declare(strict_types=1);

namespace App\Core\Auth\Actions;

use App\Core\Auth\Enums\EmailVerificationOutcome;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use App\Core\Auth\Support\EmailVerificationResult;

/**
 * Reenvia o link de verificação de e-mail, respeitando o intervalo mínimo
 * entre envios. Sem nada pendente (exigência desligada ou e-mail já
 * confirmado), não envia nada.
 */
final class ResendEmailVerification
{
    public function handle(User $user): EmailVerificationResult
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
