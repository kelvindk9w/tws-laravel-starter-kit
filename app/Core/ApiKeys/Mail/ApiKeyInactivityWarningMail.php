<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Mail;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Mail\KitMailable;

/**
 * Aviso PRÉVIO de expiração por inatividade (ADR-006): enviado Y dias antes
 * da desativação automática (config api_keys.inactivity.warning_days).
 *
 * SEMPRE enfileirado (KitMailable). Contém apenas identificadores públicos
 * da chave (nome, código público, chave pública) — nunca a secreta.
 * Strings via __() (ADR-007): ver lang/{pt_BR,en,es}/mail.php.
 */
final class ApiKeyInactivityWarningMail extends KitMailable
{
    public function __construct(
        public readonly ApiKey $apiKey,
        public readonly int $expiresInDays,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.api_key_inactivity.subject', ['platform' => platform()->name]);
    }

    protected function messageView(): string
    {
        return 'mail.messages.api-key-inactivity-warning';
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageData(): array
    {
        return [
            'keyName' => $this->apiKey->name,
            'keyPublicCode' => $this->apiKey->codigo_publico,
            'keyPublicKey' => $this->apiKey->public_key,
        ];
    }
}
