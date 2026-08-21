<?php

declare(strict_types=1);

namespace App\Core\Contact\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mensagem do formulário de contato da landing → e-mail do time
 * (PLATFORM_CONTACT_EMAIL). SEMPRE enfileirado (ShouldQueue); em dev,
 * visível no Mailpit. replyTo = remetente do formulário (responder direto).
 */
final class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable;

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
            subject: __('contact.mail.subject_line', [
                'platform' => platform()->name,
                'subject' => __("contact.subjects.{$this->subjectKey}"),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            with: [
                'subjectLabel' => __("contact.subjects.{$this->subjectKey}"),
            ],
        );
    }
}
