<?php

declare(strict_types=1);

namespace App\Core\Auth\Actions;

use App\Core\Auth\Enums\EmailVerificationOutcome;
use App\Core\Auth\Models\User;
use App\Core\Auth\Support\EmailVerification;
use App\Core\Auth\Support\EmailVerificationResult;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

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
    public function handle(Request $request, User $user, string $uuid, string $hash): EmailVerificationResult
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
