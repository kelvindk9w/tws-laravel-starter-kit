<?php

declare(strict_types=1);

namespace App\Core\Auth\Mail;

use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Mail\KitMailable;

/**
 * E-mail com o código de verificação (2FA por e-mail — ADR-006).
 *
 * SEMPRE enfileirado (KitMailable é ShouldQueue → Redis em dev/produção;
 * Mailpit como SMTP de dev). O código em claro existe apenas neste payload
 * transitório do job e no e-mail — no banco fica somente o hash
 * (verification_codes).
 *
 * Assunto, corpo e versão em texto puro vêm do layout único do kit
 * (KitMailable + <x-email::layouts.kit>). Strings via __() (ADR-007):
 * ver lang/{pt_BR,en,es}/mail.php.
 */
final class VerificationCodeMail extends KitMailable
{
    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.verification_code.subject', ['platform' => platform()->name]);
    }

    protected function messageView(): string
    {
        return 'mail.messages.verification-code';
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageData(): array
    {
        return [
            'expiresInMinutes' => (int) config('auth.verification.code_ttl_minutes', 10),
        ];
    }
}
