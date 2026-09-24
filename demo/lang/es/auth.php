<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra demo/lang como caminho extra de tradução, e o Laravel mescla este
// arquivo com lang/es/auth.php: as chaves continuam `auth.demo_account.*` e
// `auth.ui.demo_*`.

return [

    'demo_account' => [
        'update_blocked' => 'Cuenta de demostración protegida: ":email" no acepta cambios en :fields. Nombre, foto, idioma y tema siguen editables.',
        'delete_blocked' => 'Cuenta de demostración protegida: ":email" no se puede eliminar.',
    ],

    'ui' => [
        // Login demo: só quando o modo demo está ligado (DemoSurface).
        'demo_notice' => 'Entorno de demostración: las credenciales ya vienen completadas, solo entra.',
        'demo_credentials' => 'Usuario demo',
    ],

];
