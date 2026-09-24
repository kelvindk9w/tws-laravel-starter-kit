<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Auth\Enums\EmailVerificationOutcome;

/**
 * O que aconteceu na verificação de e-mail, para a resposta decidir o que mostrar.
 */
final readonly class EmailVerificationResult
{
    /**
     * @param  int  $seconds  Espera restante, quando o resultado é Cooldown.
     */
    public function __construct(
        public EmailVerificationOutcome $outcome,
        public int $seconds = 0,
    ) {}
}
