<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra demo/lang como caminho extra de tradução, e o Laravel mescla este
// arquivo com lang/en/auth.php: as chaves continuam `auth.demo_account.*` e
// `auth.ui.demo_*`.

return [

    'demo_account' => [
        'update_blocked' => 'Protected demo account: ":email" does not accept changes to :fields. Name, photo, language and theme remain editable.',
        'delete_blocked' => 'Protected demo account: ":email" cannot be deleted.',
    ],

    'ui' => [
        // Login demo: só quando o modo demo está ligado (DemoSurface).
        'demo_notice' => 'Demo environment: the credentials below are already filled in, just sign in.',
        'demo_credentials' => 'Demo user',
    ],

];
