<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * Estado de uma entrega (um evento para um endpoint).
 *
 * - pending: aguardando a primeira tentativa (ou um reenvio manual);
 * - delivering: uma tentativa em andamento (com prazo — `locked_until`);
 * - retrying: falhou e tem nova tentativa marcada (`next_attempt_at`);
 * - succeeded: o receptor respondeu 2xx;
 * - paused: o endpoint está DESATIVADO (à mão ou por falhas): a entrega
 *   espera, sem sair, e volta a `pending` quando ele é reativado (dentro do
 *   prazo de retenção). Nunca se perde por causa da desativação;
 * - failed: esgotou as tentativas, ou ficou pausada além da retenção.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivering = 'delivering';
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Paused = 'paused';

    public function label(): string
    {
        return __('webhooks.delivery_status.'.$this->value);
    }

    /**
     * Ainda pode sair (automática ou manualmente)?
     */
    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    /**
     * Os estados que o outbox e o worker ainda enviam.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Delivering, self::Retrying];
    }

    /**
     * Os valores de open(), para consultas.
     *
     * @return list<string>
     */
    public static function openValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::open());
    }
}
