<?php

// =============================================================================
// Landing pública, showcase de componentes (/ui) e login demo.
//
// Segurança: as flags abaixo são a SEGUNDA barreira, não a única. Em
// APP_ENV=production a superfície de demonstração (contas demo, vitrine /ui,
// galeria /mail-preview e os seeders de dado fictício) é recusada mesmo com as
// flags ligadas — quem decide é App\Core\Support\DemoSurface. As flags
// continuam servindo para desligar a demo FORA de produção.
//
// Único jeito de ter demonstração em produção: declarar o opt-out
// DEMO_ALLOW_IN_PRODUCTION=true (abaixo). Esquecimento não autoriza nada.
// =============================================================================

return [

    'demo' => [
        // ESCAPE HATCH da superfície de demonstração em produção.
        //
        // O roadmap do kit prevê uma demo pública hospedada (com reset
        // automático) — essa é uma demonstração legitimamente rodando em
        // produção, e ela precisa de um caminho. Este é o caminho, e ele é
        // DECLARADO: sem valor padrão verdadeiro, sem aparecer descomentado em
        // nenhum .env de exemplo, e com aviso no log a cada boot enquanto
        // estiver ligado (AppServiceProvider).
        //
        // Ligar isto significa dizer: "este banco é descartável e estas
        // credenciais são públicas". Nunca ligue numa instalação com dado real.
        'allow_in_production' => (bool) env('DEMO_ALLOW_IN_PRODUCTION', false),
    ],

    // Showcase de componentes UI em /ui (documentação viva do kit).
    // Ler sempre por DemoSurface::showcaseEnabled(), nunca esta chave crua: é
    // ela que soma o fail-closed de produção a esta flag.
    'showcase_enabled' => (bool) env('UI_SHOWCASE_ENABLED', env('APP_ENV') === 'local'),

    // As senhas demo obedecem à MESMA política de senha do app
    // (config/auth.php password_rules: 12+ caracteres, maiúscula+minúscula e
    // dígito). Senha demo reprovada pela própria validação do produto é
    // inconsistência, não conveniência (bug de QA #6).
    'demo_login' => [
        // Pré-preenche credenciais demo na tela de login e ativa o DemoUserSeeder.
        // Ler sempre por DemoSurface::loginEnabled(), nunca esta chave crua: é
        // ela que soma o fail-closed de produção a esta flag.
        'enabled' => (bool) env('DEMO_LOGIN_ENABLED', env('APP_ENV') === 'local'),
        'email' => env('DEMO_USER_EMAIL', 'demo@tws.dev'),
        'password' => env('DEMO_USER_PASSWORD', 'Demo-password1'),
    ],

    // Super admin demo (/admin): mesma flag do login demo (demo_login.enabled).
    // Pré-preenche as credenciais no login do Filament e ativa o DemoAdminSeeder.
    'demo_admin' => [
        'email' => env('DEMO_ADMIN_EMAIL', 'admin@tws.dev'),
        'password' => env('DEMO_ADMIN_PASSWORD', 'Demo-admin-password1'),
    ],

    // Estratégia de exibição de erros de validação nos formulários clássicos
    // (POST + redirect). Override por formulário: <x-form-errors display="…">
    // e field_error('campo', '…').
    //   inline  → erro embaixo de cada campo (prop :error dos inputs)
    //   summary → só o resumo <x-form-errors> no topo, com âncoras p/ os campos
    //   toast   → erros disparam o toast do kit
    //   both    → inline + resumo
    'error_display' => env('UI_ERROR_DISPLAY', 'inline'),

];
