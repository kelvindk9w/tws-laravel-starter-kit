<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra a pasta lang/ do pacote (o aplicativo vence), e o Laravel mescla este
// arquivo com lang/pt_BR/auth.php: as chaves continuam `auth.demo_account.*` e
// `auth.ui.demo_*`.

return [

    'demo_account' => [
        'update_blocked' => 'Conta de demonstração protegida: ":email" não aceita alteração de :fields. Nome, foto, idioma e tema continuam liberados.',
        'delete_blocked' => 'Conta de demonstração protegida: ":email" não pode ser excluída.',
    ],

    'ui' => [
        // Login demo: só quando o modo demo está ligado (DemoSurface).
        'demo_notice' => 'Ambiente de demonstração: as credenciais abaixo já vêm preenchidas, basta entrar.',
        'demo_credentials' => 'Usuário demo',
    ],

    // Texto "de demo" da mensagem NEUTRA do produto sobre conta protegida
    // (twstec/kit-auth): com a demonstração instalada, a conta protegida é a
    // conta demo. Sem ela, vale o texto neutro do pacote.
    'two_factor' => [
        'account_protected' => 'Indisponível na conta de demonstração: ligar a verificação em duas etapas trancaria a demo para os próximos visitantes.',
        'demo_blocked' => 'Indisponível na conta de demonstração: ligar a verificação em duas etapas trancaria a demo para os próximos visitantes.',
    ],

];
