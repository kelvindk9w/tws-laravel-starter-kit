<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use Twstec\Kit\Auth\Enums\TwoFactorChallengeOutcome;

/**
 * O que aconteceu na tela do código, para a resposta decidir o que mostrar.
 */
final readonly class TwoFactorChallengeResult
{
    /**
     * @param  string|null  $message  Motivo já traduzido, quando o resultado é Abandoned.
     * @param  int  $seconds  Espera restante, quando o resultado é ResendCooldown.
     */
    public function __construct(
        public TwoFactorChallengeOutcome $outcome,
        public ?string $message = null,
        public int $seconds = 0,
    ) {}
}
