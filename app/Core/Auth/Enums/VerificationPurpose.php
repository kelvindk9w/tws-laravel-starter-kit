<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Finalidade de um código de verificação. Códigos de finalidades diferentes
 * são independentes (um código de ação sensível não vale para outro fluxo).
 */
enum VerificationPurpose: string
{
    /** Confirmação de ação sensível (saque, rotação de chave etc.). */
    case SensitiveAction = 'sensitive_action';
}
