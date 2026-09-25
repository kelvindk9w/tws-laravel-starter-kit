<?php

// Chaves do grupo `auth` que pertencem à DEMONSTRAÇÃO. O DemoServiceProvider
// registra a pasta lang/ do pacote (o aplicativo vence), e o Laravel mescla este
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

    // "Demo" wording of the product's NEUTRAL protected-account message
    // (twstec/kit-auth): with the demo installed, the protected account is the
    // demo account. Without it, the package's neutral text applies.
    'two_factor' => [
        'account_protected' => 'Not available on the demo account: turning on two-step verification would lock the demo for the next visitors.',
        'demo_blocked' => 'Not available on the demo account: turning on two-step verification would lock the demo for the next visitors.',
    ],

];
