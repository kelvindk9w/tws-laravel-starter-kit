<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

use Carbon\CarbonImmutable;

/**
 * O que o parser leu de um corpo verificado: id do evento (null = o pacote
 * usa o id assinado pelo esquema ou, sem ele, o hash do corpo), tipo, data e
 * o payload decodificado.
 */
final readonly class ParsedEvent
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function __construct(
        public ?string $id,
        public ?string $type,
        public ?CarbonImmutable $occurredAt,
        public array $payload,
    ) {}
}
