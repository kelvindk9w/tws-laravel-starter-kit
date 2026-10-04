<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * Estado de um endpoint. Só `active` recebe entregas.
 */
enum EndpointStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return __('webhooks.status.'.$this->value);
    }
}
