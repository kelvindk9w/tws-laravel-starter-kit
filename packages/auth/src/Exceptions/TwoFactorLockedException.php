<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Exceptions;

use RuntimeException;

/**
 * Códigos errados demais no segundo fator do login — por conta ou por IP.
 * Carrega quantos segundos faltam para o bloqueio acabar (TwoFactorLogin).
 */
final class TwoFactorLockedException extends RuntimeException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct('Verificação em duas etapas bloqueada por excesso de tentativas.');
    }

    /**
     * Mensagem traduzida, pronta para a tela.
     */
    public function userMessage(): string
    {
        return __('auth.two_factor.locked', [
            'seconds' => $this->seconds,
            'minutes' => max(1, (int) ceil($this->seconds / 60)),
        ]);
    }
}
