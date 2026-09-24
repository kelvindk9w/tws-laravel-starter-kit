<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

/**
 * Preferência da verificação em duas etapas no login
 * (`two_factor_enabled_at`). A regra — quem liga, quem desliga, o código e os
 * limites — mora em Services\TwoFactorLogin.
 */
trait HasTwoFactor
{
    public function initializeHasTwoFactor(): void
    {
        $this->mergeCasts(['two_factor_enabled_at' => 'datetime']);
    }
}
