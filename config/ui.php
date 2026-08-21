<?php

// =============================================================================
// Landing pública, showcase de componentes (/ui) e login demo.
//
// Segurança: os padrões só são "ligados" em APP_ENV=local. Em produção,
// UI_SHOWCASE_ENABLED=false (a rota /ui responde 404) e o login demo NUNCA
// é habilitado (credenciais conhecidas pré-preenchidas seriam uma backdoor).
// =============================================================================

return [

    // Showcase de componentes UI em /ui (documentação viva do kit).
    'showcase_enabled' => (bool) env('UI_SHOWCASE_ENABLED', env('APP_ENV') === 'local'),

    'demo_login' => [
        // Pré-preenche credenciais demo na tela de login e ativa o DemoUserSeeder.
        'enabled' => (bool) env('DEMO_LOGIN_ENABLED', env('APP_ENV') === 'local'),
        'email' => env('DEMO_USER_EMAIL', 'demo@tws.dev'),
        'password' => env('DEMO_USER_PASSWORD', 'demo-password'),
    ],

];
