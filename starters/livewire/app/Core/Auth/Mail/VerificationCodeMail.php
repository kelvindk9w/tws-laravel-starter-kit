<?php

declare(strict_types=1);

namespace App\Core\Auth\Mail;

use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Mail\KitMailable;

/**
 * E-mail com o código de verificação (2FA por e-mail).
 *
 * SEMPRE enfileirado (KitMailable é ShouldQueue → Redis em dev/produção;
 * Mailpit como SMTP de dev). O código em claro existe apenas neste payload
 * transitório do job e no e-mail — no banco fica somente o hash
 * (verification_codes).
 *
 * Assunto, corpo e versão em texto puro vêm do layout único do kit
 * (KitMailable + <x-email::layouts.kit>). Strings via __() (nada hardcoded):
 * ver lang/{pt_BR,en,es}/mail.php.
 *
 * O TEXTO segue a finalidade do código: o de ação sensível fala em
 * "confirmar a ação"; o do login (verificação em duas etapas) fala em
 * "concluir a entrada" e, no aviso final, diz o que significa receber esse
 * e-mail sem ter tentado entrar — alguém tem a sua senha.
 */
final class VerificationCodeMail extends KitMailable
{
    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
    ) {}

    protected function subjectLine(): string
    {
        return __($this->copyKey().'.subject', ['platform' => platform()->name]);
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
            'copy' => $this->copyKey(),
        ];
    }

    /**
     * Grupo de strings do e-mail conforme a finalidade do código.
     */
    private function copyKey(): string
    {
        return match ($this->purpose) {
            VerificationPurpose::LoginChallenge => 'mail.login_code',
            VerificationPurpose::SensitiveAction => 'mail.verification_code',
        };
    }
}
