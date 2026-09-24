<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Resultados da verificação de e-mail do cadastro (VerifyEmail e
 * ResendEmailVerification). O link aceito vira resposta pelo contrato
 * VerifyEmailResponse; todos os outros, pelo EmailVerificationResponse.
 */
enum EmailVerificationOutcome
{
    /** Link aceito: e-mail confirmado (agora ou antes, pelo mesmo link). */
    case Verified;

    /** Nada pendente: exigência desligada ou e-mail já confirmado. */
    case NotPending;

    /** Link aberto com outra conta logada. */
    case WrongAccount;

    /** Link inválido, adulterado ou vencido. */
    case InvalidLink;

    /** Novo link enviado. */
    case LinkSent;

    /** Pedido de novo link antes do intervalo mínimo (ver `seconds`). */
    case Cooldown;
}
