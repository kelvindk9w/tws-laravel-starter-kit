<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Enums;

/**
 * Por que um endpoint está desativado: à mão (`manual`) ou pelo pacote,
 * depois de N tentativas falhas seguidas (`failures`).
 */
enum DisabledReason: string
{
    case Manual = 'manual';
    case Failures = 'failures';

    public function label(): string
    {
        return __('webhooks.disabled_reason.'.$this->value);
    }
}
