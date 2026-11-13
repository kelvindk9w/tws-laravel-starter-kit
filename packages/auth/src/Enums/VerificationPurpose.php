<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Enums;

/**
 * Finalidade de um código de verificação. Códigos de finalidades diferentes
 * são independentes (um código de ação sensível não vale para outro fluxo).
 */
enum VerificationPurpose: string
{
    /** Confirmação de ação sensível (rotação de chave, exclusão de conta etc.). */
    case SensitiveAction = 'sensitive_action';

    /** Segundo fator do login (verificação em duas etapas por e-mail). */
    case LoginChallenge = 'login_challenge';

    /**
     * Configuração do segundo fator OBRIGATÓRIO (a tela para onde vai quem
     * ainda não ligou — Http\Controllers\TwoFactorSetupController). Mesma
     * exigência da ação sensível (senha de transação + código por e-mail),
     * mas família própria: o código dela não vale na confirmação de
     * segurança (nem o contrário), e o intervalo de reenvio de uma não conta
     * para a outra — quem acabou de configurar não espera para a primeira
     * ação sensível.
     */
    case TwoFactorSetup = 'two_factor_setup';

    /**
     * Confirmação SÓ POR CÓDIGO (sem a senha de transação), para a ação cuja
     * política aceita só o código — hoje, criar chave de API quando a
     * política do ambiente das chaves é `code` (twstec/kit-accounts,
     * ApiKeyCreationConfirmation). Família própria, de propósito: o código
     * dela NUNCA vira token de ação sensível (o `sensitive.token` e o
     * SensitiveActionService só aceitam o código de SensitiveAction, que
     * exige a senha de transação), então dispensar a senha num fluxo não a
     * dispensa em nenhum outro.
     */
    case CodeConfirmation = 'code_confirmation';
}
