<?php

declare(strict_types=1);

namespace App\Core\Contact\Mail;

use App\Core\Mail\KitMailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mensagem do formulário de contato da landing → e-mail do time
 * (PLATFORM_CONTACT_EMAIL). SEMPRE enfileirado (KitMailable); em dev,
 * visível no Mailpit. replyTo = remetente do formulário (responder direto).
 *
 * Único e-mail do kit que sobrescreve o envelope: além do assunto, precisa do
 * replyTo. O corpo e o texto puro continuam vindo do layout único.
 */
final class ContactMessageMail extends KitMailable
{
    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $subjectKey,
        public readonly string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail, $this->senderName)],
            subject: $this->subjectLine(),
        );
    }

    protected function subjectLine(): string
    {
        return __('contact.mail.subject_line', [
            'platform' => platform()->name,
            'subject' => __("contact.subjects.{$this->subjectKey}"),
        ]);
    }

    protected function messageView(): string
    {
        return 'mail.messages.contact-message';
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageData(): array
    {
        return [
            'subjectLabel' => __("contact.subjects.{$this->subjectKey}"),
        ];
    }
}
