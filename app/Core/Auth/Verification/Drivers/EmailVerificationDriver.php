<?php

declare(strict_types=1);

namespace App\Core\Auth\Verification\Drivers;

use App\Core\Auth\Contracts\VerificationChannelDriver;
use App\Core\Auth\Enums\VerificationChannel;
use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Mail\VerificationCodeMail;
use App\Core\Auth\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Driver de verificação por E-MAIL (canal padrão do MVP — ADR-006).
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

    public function send(User $user, string $code, VerificationPurpose $purpose): void
    {
        Mail::to($user)->queue(new VerificationCodeMail($code, $purpose));
    }
}
