<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

use RuntimeException;

/**
 * O destino de um webhook foi RECUSADO (SSRF e afins). `reason` é um código
 * estável (`invalid_url`, `scheme_not_allowed`, `credentials_in_url`,
 * `invalid_host`, `unresolvable`, `private_address`, `metadata_address`); a
 * mensagem é a traduzida (`webhooks.destination.<reason>`), sem a URL — para
 * o log e a trilha. Para a PESSOA (tela, log de entregas), publicMessage():
 * os motivos que dependem da rede (`unresolvable`, `private_address`,
 * `metadata_address`) saem com o MESMO texto genérico, para a tela não dizer
 * quais nomes internos existem.
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

    /**
     * Os motivos que revelam algo da rede (o nome existe? aponta para dentro?).
     *
     * @var list<string>
     */
    public const NETWORK_REASONS = ['unresolvable', 'private_address', 'metadata_address'];

    /**
     * O texto para a pessoa: genérico para os motivos de rede, o próprio para
     * os de formato (URL, esquema, credencial, host malformado).
     */
    public function publicMessage(): string
    {
        return in_array($this->reason, self::NETWORK_REASONS, true)
            ? __('webhooks.destination.not_allowed')
            : $this->getMessage();
    }
}
