<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Illuminate\Http\Request;

/**
 * O que um verificador enxerga de um webhook recebido: o CORPO CRU (os bytes
 * exatamente como chegaram, antes de qualquer decodificação), os cabeçalhos
 * (nome em minúsculas) e a hora da chegada (segundos Unix — o "agora" da
 * janela de tempo).
 *
 * Nunca vai para log nem trilha: `__debugInfo` esconde o corpo e os
 * cabeçalhos (um `dump()` ou um erro com o objeto não os imprime).
 */
final readonly class InboundRequest
{
    /**
     * @param  array<string, list<string>>  $headers  nome em minúsculas => valores
     */
    public function __construct(
        #[\SensitiveParameter] public string $body,
        #[\SensitiveParameter] public array $headers,
        public int $receivedAt,
        public ?string $ip = null,
    ) {}

    public static function fromHttp(Request $request, int $receivedAt): self
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values(array_map(
                static fn (mixed $value): string => (string) $value,
                array_filter((array) $values, static fn (mixed $value): bool => $value !== null),
            ));
        }

        return new self($request->getContent(), $headers, $receivedAt, $request->ip());
    }

    /**
     * O primeiro valor do cabeçalho (nome sem diferença de maiúsculas), ou
     * null quando ele não veio.
     */
    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : $values[0];
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'body_bytes' => strlen($this->body),
            'headers' => array_keys($this->headers),
            'receivedAt' => $this->receivedAt,
            'ip' => $this->ip,
        ];
    }
}
