<?php

declare(strict_types=1);

namespace App\Core\Auth\Mail;

use App\Core\Auth\Enums\VerificationPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail com o código de verificação (2FA por e-mail — ADR-006).
 *
 * SEMPRE enfileirado (ShouldQueue → Redis em dev/produção; Mailpit como
 * SMTP de dev). O código em claro existe apenas neste payload transitório
 * do job e no e-mail — no banco fica somente o hash (verification_codes).
 *
 * Strings via __() (ADR-007): ver lang/pt_BR/mail.php.
 */
final class VerificationCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verification_code.subject', ['platform' => platform()->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'expiresInMinutes' => (int) config('auth.verification.code_ttl_minutes', 10),
            ],
        );
    }
}
