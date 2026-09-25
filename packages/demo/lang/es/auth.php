<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra a pasta lang/ do pacote (o aplicativo vence), e o Laravel mescla este
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

    // Texto "de demo" del mensaje NEUTRO del producto sobre cuenta protegida
    // (twstec/kit-auth): con la demostración instalada, la cuenta protegida es
    // la cuenta demo. Sin ella, vale el texto neutro del paquete.
    'two_factor' => [
        'account_protected' => 'No disponible en la cuenta de demostración: activar la verificación en dos pasos bloquearía la demo para los próximos visitantes.',
        'demo_blocked' => 'No disponible en la cuenta de demostración: activar la verificación en dos pasos bloquearía la demo para los próximos visitantes.',
    ],

];
