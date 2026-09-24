<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Actions;

use Illuminate\Support\Facades\Password;

/**
 * Pede o link de redefinição de senha pelo broker nativo do Laravel (token
 * guardado só como hash, com expiração — config auth.passwords.users.expire).
 *
 * Anti-enumeração: não devolve nada. Quem chama responde SEMPRE igual,
 * existindo ou não o e-mail (contrato PasswordResetLinkSentResponse).
 */
final class SendPasswordResetLink
{
    public function handle(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }
}
