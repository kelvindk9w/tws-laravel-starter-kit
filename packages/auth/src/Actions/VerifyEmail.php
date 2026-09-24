<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\EmailVerificationOutcome;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Auth\Support\EmailVerificationResult;

/**
 * Confere o link de verificação de e-mail aberto pela conta logada.
 *
 * Assinatura relativa, expiração, `uuid` da conta e hash do e-mail (ver
 * EmailVerification). O link precisa ser aberto com a PRÓPRIA conta: uuid de
 * outra conta é WrongAccount, antes de qualquer outra conferência. Link
 * aceito confirma o e-mail (uma vez só) e dispara o evento Verified.
 */
final class VerifyEmail
{
    public function handle(Request $request, AuthUser $user, string $uuid, string $hash): EmailVerificationResult
    {
        if (! hash_equals((string) $user->uuid, $uuid)) {
            return new EmailVerificationResult(EmailVerificationOutcome::WrongAccount);
        }

        if (! EmailVerification::linkIsValidFor($request, $user, $uuid, $hash)) {
            return new EmailVerificationResult(EmailVerificationOutcome::InvalidLink);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return new EmailVerificationResult(EmailVerificationOutcome::Verified);
    }
}
