<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

/**
 * O resultado de um verificador: aceito (com o id do evento e o carimbo de
 * tempo que o próprio esquema assinou, quando há) ou recusado (com o motivo,
 * só para o log interno).
 */
final readonly class VerificationResult
{
    private function __construct(
        public bool $passed,
        public ?Rejection $rejection = null,
        public ?string $eventId = null,
        public ?int $timestamp = null,
    ) {}

    /**
     * @param  string|null  $eventId  id do evento que vem ASSINADO no esquema (ex.: `webhook-id`)
     * @param  int|null  $timestamp  carimbo de tempo assinado, em segundos Unix
     */
    public static function passed(?string $eventId = null, ?int $timestamp = null): self
    {
        return new self(true, null, $eventId, $timestamp);
    }

    public static function failed(Rejection $rejection): self
    {
        return new self(false, $rejection);
    }
}
