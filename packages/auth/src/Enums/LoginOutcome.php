<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Enums;

/**
 * Resultado de uma senha aceita no login (AttemptLogin). Senha errada,
 * conta inativa e bloqueio por tentativas não são resultado: são
 * ValidationException, com a mensagem de sempre.
 */
enum LoginOutcome
{
    /** Sessão autenticada, com ID novo. */
    case Authenticated;

    /** Conta com segundo fator: estado intermediário aberto e código enviado; nada autenticado ainda. */
    case TwoFactorRequired;
}
