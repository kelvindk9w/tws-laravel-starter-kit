<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Preferência da verificação em duas etapas no login
 * (`two_factor_enabled_at`). A regra — quem liga, quem desliga, o código e os
 * limites — mora em Services\TwoFactorLogin.
 *
 * Coluna com o cast desta trait (para a análise estática — Larastan/PHPStan
 * — do model que a usa: o cast entra em tempo de execução e o analisador não
 * o vê):
 *
 * @property Carbon|null $two_factor_enabled_at
 */
trait HasTwoFactor
{
    public function initializeHasTwoFactor(): void
    {
        $this->mergeCasts(['two_factor_enabled_at' => 'datetime']);
    }
}
