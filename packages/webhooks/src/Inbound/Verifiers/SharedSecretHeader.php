<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\Rejection;
use Twstec\Kit\Webhooks\Inbound\VerificationResult;

/**
 * SEGREDO COMPARTILHADO EM CABEÇALHO (driver `header_token`): o provedor
 * manda um token fixo num cabeçalho (`asaas-access-token: …`,
 * `Authorization: Bearer …`), e ele é comparado em tempo constante com cada
 * segredo vigente.
 *
 * É a forma MAIS FRACA: o token não assina o corpo nem tem carimbo de tempo
 * — prova só que quem mandou conhece o segredo. Use HTTPS, a allowlist de IP
 * quando o provedor publica as faixas e, no handler, RECONSULTE a API do
 * provedor antes de mudar estado. A deduplicação pelo id do evento continua
 * valendo.
 *
 *     'verifier' => [
 *         'driver' => 'header_token',
 *         'header' => 'asaas-access-token',
 *         'prefix' => '',                // ex.: 'Bearer '
 *         'secrets' => [env('X_WEBHOOK_TOKEN'), env('X_WEBHOOK_TOKEN_PREVIOUS')],
 *     ],
 */
final readonly class SharedSecretHeader implements Verifier
{
    /**
     * @var list<string>
     */
    private array $secrets;

    /**
     * @param  array<array-key, mixed>|string|null  $secrets
     */
    public function __construct(
        private string $header,
        #[\SensitiveParameter] array|string|null $secrets,
        private string $prefix = '',
    ) {
        if (trim($header) === '') {
            throw new InvalidSourceConfiguration('header_token: informe o cabeçalho (header).');
        }

        $this->secrets = SignatureTools::secrets($secrets);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromConfig(array $options): self
    {
        $secrets = $options['secrets'] ?? $options['secret'] ?? null;

        return new self(
            SignatureTools::string($options, 'header'),
            is_array($secrets) || is_string($secrets) ? $secrets : null,
            SignatureTools::string($options, 'prefix'),
        );
    }

    public function verify(InboundRequest $request): VerificationResult
    {
        if ($this->secrets === []) {
            return VerificationResult::failed(Rejection::NotConfigured);
        }

        $provided = $request->header($this->header);

        if ($provided === null || $provided === '') {
            return VerificationResult::failed(Rejection::MissingSignature);
        }

        if ($this->prefix !== '') {
            if (! str_starts_with($provided, $this->prefix)) {
                return VerificationResult::failed(Rejection::MalformedSignature);
            }

            $provided = substr($provided, strlen($this->prefix));
        }

        $matched = false;

        foreach ($this->secrets as $secret) {
            $matched = hash_equals($secret, $provided) || $matched;
        }

        return $matched
            ? VerificationResult::passed()
            : VerificationResult::failed(Rejection::InvalidSignature);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['header' => $this->header, 'secrets' => count($this->secrets)];
    }
}
