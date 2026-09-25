<?php

declare(strict_types=1);

return [
    'personal_account' => 'Conta pessoal',

    'roles' => [
        'owner' => 'Dono',
        'admin' => 'Administrador',
        'member' => 'Membro',
    ],

    'context' => [
        'missing' => 'Consulta de :model sem conta atual. Dados de conta só são lidos com uma conta definida (sessão do painel ou chave de API) ou em modo sistema explícito: Accounts::asSystem(\'motivo\', fn () => ...) ou Accounts::actingAs($conta, fn () => ...).',
        'system_write_without_account' => 'Em modo sistema, :model precisa ser gravado com a conta informada (account_id).',
        'cross_account_write' => 'Gravação de :model numa conta que não é a atual foi recusada.',
        'account_change' => 'A conta de :model não muda depois de gravada.',
    ],

    'ownership' => [
        'second_owner' => 'A conta já tem um dono. A propriedade muda por transferência.',
        'role_change' => 'O papel de dono não é concedido nem retirado por aqui. A propriedade muda por transferência.',
        'owner_leaves' => 'O dono não sai da conta. Transfira a propriedade antes.',
        'moves_account' => 'Um vínculo de membro não muda de conta.',
    ],

    'members' => [
        'already_member' => 'Esta pessoa já é membro da conta.',
    ],

    'deletion' => [
        'owner_has_members' => '{1} Esta pessoa é dona de uma conta com outros membros (:accounts). Transfira a propriedade antes de excluí-la.|[2,*] Esta pessoa é dona de contas com outros membros (:accounts). Transfira a propriedade antes de excluí-la.',
    ],

    'authorization' => [
        'denied' => 'Seu papel nesta conta não permite esta ação.',
        'not_member' => 'Você não é membro desta conta.',
    ],
];
