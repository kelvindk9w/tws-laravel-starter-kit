<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Resultados do segundo passo do login (tela do código). O login concluído
 * vira resposta pelo contrato TwoFactorLoginResponse; todos os outros, pelo
 * TwoFactorChallengeResponse.
 */
enum TwoFactorChallengeOutcome
{
    /** Código certo: sessão autenticada, com ID novo. */
    case Authenticated;

    /** Código errado: a tentativa foi contada; a pessoa fica na tela do código. */
    case InvalidCode;

    /** Não há código utilizável (vencido, usado, tentativas esgotadas). */
    case ExpiredCode;

    /** Novo código enviado. */
    case CodeResent;

    /** Pedido de novo código antes do intervalo mínimo (ver `seconds`). */
    case ResendCooldown;

    /** Estado intermediário encerrado por um motivo (bloqueio, conta inativa — ver `message`). */
    case Abandoned;

    /** A pessoa desistiu do login. */
    case Cancelled;

    /** Nunca houve estado intermediário nesta sessão. */
    case Missing;

    /** Havia estado intermediário, mas ele venceu ou deixou de valer (a senha mudou). */
    case Expired;
}
