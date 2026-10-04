<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

use RuntimeException;

/**
 * O destino de um webhook foi RECUSADO (SSRF e afins). `reason` é um código
 * estável (`invalid_url`, `scheme_not_allowed`, `credentials_in_url`,
 * `invalid_host`, `unresolvable`, `private_address`, `metadata_address`); a
 * mensagem é a traduzida (`webhooks.destination.<reason>`), sem a URL.
 */
final class BlockedDestinationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $host = null,
        public readonly ?string $address = null,
    ) {
        parent::__construct(__('webhooks.destination.'.$reason));
    }
}
