<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Resultado da conferência de um código de verificação (VerificationCodes::verify).
 */
enum VerificationResult
{
    /** Código certo: foi consumido agora e não vale mais. */
    case Valid;

    /** Código errado: a tentativa foi contada. */
    case Invalid;

    /** Não há código utilizável: nunca pedido, vencido, já usado ou tentativas esgotadas. */
    case Expired;
}
