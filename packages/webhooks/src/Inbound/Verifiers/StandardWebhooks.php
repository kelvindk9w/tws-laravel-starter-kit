<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * A especificação STANDARD WEBHOOKS (driver `standard_webhooks`,
 * https://www.standardwebhooks.com): cabeçalhos `webhook-id`,
 * `webhook-timestamp` (segundos Unix) e `webhook-signature` (`v1,<base64>`,
 * várias separadas por espaço durante uma rotação); HMAC-SHA256 sobre
 * `{id}.{timestamp}.{corpo}`; segredo `whsec_<base64>`.
 *
 * O `webhook-id` é ASSINADO — vira o id do evento para a deduplicação
 * quando a fonte não diz outro.
 *
 *     'verifier' => [
 *         'driver' => 'standard_webhooks',
 *         'secrets' => [env('X_WEBHOOK_SECRET'), env('X_WEBHOOK_SECRET_PREVIOUS')],
 *         'tolerance' => 300,            // opcional
 *     ],
 *
 * É o HmacSignature com a configuração da especificação; só a assinatura
 * simétrica (`v1`) — a assimétrica (`v1a`, Ed25519) não está coberta.
 */
final readonly class StandardWebhooks implements Verifier
{
    private HmacSignature $hmac;

    /**
     * @param  array<array-key, mixed>|string|null  $secrets
     */
    public function __construct(#[\SensitiveParameter] array|string|null $secrets, int $tolerance = 300)
    {
        $this->hmac = new HmacSignature(
            header: 'webhook-signature',
            secrets: $secrets,
            algorithm: 'sha256',
            encoding: 'base64',
            format: 'versioned',
            signatureKey: 'v1',
            timestampHeader: 'webhook-timestamp',
            idHeader: 'webhook-id',
            signedContent: '{id}.{timestamp}.{body}',
            tolerance: $tolerance,
            secretPrefix: 'whsec_',
            secretEncoding: 'base64',
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromConfig(array $options): self
    {
        $secrets = $options['secrets'] ?? $options['secret'] ?? null;

        return new self(is_array($secrets) || is_string($secrets) ? $secrets : null, SignatureTools::tolerance($options));
    }

    public function verify(InboundRequest $request): VerificationResult
    {
        return $this->hmac->verify($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['scheme' => 'standard-webhooks'];
    }
}
