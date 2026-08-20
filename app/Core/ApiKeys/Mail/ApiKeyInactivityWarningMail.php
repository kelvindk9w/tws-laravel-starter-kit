<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Mail;

use App\Core\ApiKeys\Models\ApiKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso PRÉVIO de expiração por inatividade (ADR-006): enviado Y dias antes
 * da desativação automática (config api_keys.inactivity.warning_days).
 *
 * SEMPRE enfileirado (ShouldQueue). Contém apenas identificadores públicos
 * da chave (nome, código público, chave pública) — nunca a secreta.
 * Strings via __() (ADR-007): ver lang/pt_BR/mail.php.
 */
final class ApiKeyInactivityWarningMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApiKey $apiKey,
        public readonly int $expiresInDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.api_key_inactivity.subject', ['platform' => platform()->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.api-key-inactivity-warning',
            with: [
                'keyName' => $this->apiKey->name,
                'keyPublicCode' => $this->apiKey->codigo_publico,
                'keyPublicKey' => $this->apiKey->public_key,
                'expiresInDays' => $this->expiresInDays,
            ],
        );
    }
}
