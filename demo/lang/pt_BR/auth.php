<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra demo/lang como caminho extra de tradução, e o Laravel mescla este
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

];
