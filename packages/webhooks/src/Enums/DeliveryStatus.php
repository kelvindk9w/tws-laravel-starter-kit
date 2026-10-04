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
 * - failed: esgotou as tentativas, ou o endpoint foi desativado ou excluído
 *   antes de ela sair.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivering = 'delivering';
    case Retrying = 'retrying';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return __('webhooks.delivery_status.'.$this->value);
    }

    /**
     * Ainda pode sair (automática ou manualmente)?
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Delivering, self::Retrying], true);
    }
}
