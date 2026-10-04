<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Security;

/**
 * Um destino CONFERIDO: a URL canônica (remontada das partes conferidas — o
 * cURL nunca recebe o texto que a pessoa digitou), o host, a porta e o IP em
 * que a conexão vai acontecer (`address`), escolhido entre os resolvidos —
 * todos conferidos.
 */
final class ValidatedDestination
{
    /**
     * @param  list<string>  $addresses  Todos os endereços resolvidos (todos permitidos).
     */
    public function __construct(
        public readonly string $url,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
        public readonly string $address,
        public readonly array $addresses,
    ) {}

    /**
     * A entrada do CURLOPT_RESOLVE: `host:porta:endereço` (IPv6 entre
     * colchetes, como a documentação do libcurl pede).
     */
    public function curlResolveEntry(): string
    {
        $address = str_contains($this->address, ':') ? '['.$this->address.']' : $this->address;

        return $this->hostForCurl().':'.$this->port.':'.$address;
    }

    /**
     * O host como o cURL o vê na URL (IPv6 literal sem colchetes).
     */
    public function hostForCurl(): string
    {
        return trim($this->host, '[]');
    }
}
