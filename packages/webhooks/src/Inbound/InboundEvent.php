<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Carbon\CarbonImmutable;

/**
 * O EVENTO RECEBIDO que o handler do aplicativo recebe — já verificado e não
 * duplicado.
 *
 * - `source`: o nome da fonte (`asaas`, `pagamentos`…), o da URL.
 * - `id`: o id do evento no provedor (o que deduplica, com a fonte). Sem id
 *   no corpo nem no esquema, é `sha256:<hash do corpo>`.
 * - `type`: o tipo do evento no provedor, quando a fonte diz onde lê-lo.
 * - `occurredAt`: quando aconteceu no provedor (ou o carimbo assinado).
 * - `payload`: o corpo decodificado. Não passou pelo filtro de ataques do
 *   kit — trate como entrada não confiável.
 * - `receivedAt`: quando chegou aqui; `receipt`: o uuid do registro de
 *   recebimento (webhook_inbound_events), para correlacionar.
 * - `attempt`: a tentativa de processamento (1 na primeira).
 *
 * `__debugInfo` esconde o payload (um `dump()` não o imprime).
 */
final readonly class InboundEvent
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        public string $source,
        public string $id,
        public ?string $type,
        public ?CarbonImmutable $occurredAt,
        public array $payload,
        public CarbonImmutable $receivedAt,
        public string $receipt,
        public int $attempt = 1,
    ) {}

    /**
     * Um valor do payload em notação de ponto (`payment.id`).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'source' => $this->source,
            'id' => $this->id,
            'type' => $this->type,
            'occurredAt' => $this->occurredAt?->toIso8601String(),
            'receivedAt' => $this->receivedAt->toIso8601String(),
            'receipt' => $this->receipt,
            'attempt' => $this->attempt,
            'payload' => '['.count($this->payload).' chave(s) ocultas]',
        ];
    }
}
