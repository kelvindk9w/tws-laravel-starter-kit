<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Verification\Drivers;

use Illuminate\Support\Facades\Mail;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\VerificationChannelDriver;
use Twstec\Kit\Auth\Enums\VerificationChannel;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;

/**
 * Driver de verificação por E-MAIL (canal padrão do kit).
 *
 * O envio é enfileirado (Redis em produção/dev, Mailpit como SMTP de dev):
 * a requisição do usuário nunca espera o SMTP. O e-mail contém apenas o
 * código e a validade — nunca links com segredos reutilizáveis.
 */
final class EmailVerificationDriver implements VerificationChannelDriver
{
    public function channel(): VerificationChannel
    {
        return VerificationChannel::Email;
    }

    public function send(AuthUser $user, string $code, VerificationPurpose $purpose): void
    {
        // Locale do destinatário: usuário com preferência salva
        // recebe o código no idioma escolhido, mesmo em fluxo de visitante
        // (ex.: reset de senha com cookie de idioma diferente).
        Mail::to($user)->locale($user->preferredLocale())->queue(new VerificationCodeMail($code, $purpose));
    }
}
