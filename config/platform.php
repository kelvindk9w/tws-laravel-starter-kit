<?php

// =============================================================================
// Configuração centralizada da plataforma (ADR-007 — regra inegociável)
//
// TODOS os dados institucionais (nome, logo, URLs, contatos, CNPJ) vêm daqui,
// sempre lidos do .env. É PROIBIDO hardcodar qualquer um desses valores em
// código, views ou configs. Alterar aqui (via .env) = sistema inteiro reflete.
//
// Acesso tipado em runtime: helper global platform() (App\Core\Support\Platform).
// =============================================================================

return [

    // Nome público da plataforma (telas, e-mails, metadados).
    'name' => env('PLATFORM_NAME', 'TWS Starter Kit'),

    // Versão da plataforma (endpoint /api/health, rodapés, suporte).
    'version' => env('PLATFORM_VERSION', '0.2.0'),

    // URL do logotipo oficial (nunca caminho hardcoded em views).
    'logo_url' => env('PLATFORM_LOGO_URL'),

    // Cor primária da marca (hex) — painéis web e super admin refletem daqui.
    'primary_color' => env('PLATFORM_PRIMARY_COLOR', '#0284C7'),

    // URL institucional oficial do produto.
    'official_url' => env('PLATFORM_OFFICIAL_URL', env('APP_URL', 'http://localhost:8180')),

    // E-mail público de suporte.
    'support_email' => env('PLATFORM_SUPPORT_EMAIL'),

    // CNPJ da empresa operadora (rodapés, termos, notas).
    'cnpj' => env('PLATFORM_CNPJ'),

    // Locale padrão da plataforma (MVP: pt-BR; multi-idioma preparado via lang/).
    'locale' => env('PLATFORM_LOCALE', 'pt_BR'),

    // Idiomas disponíveis na UI (seletor de idioma + middleware SetLocale).
    // O padrão do kit continua sendo 'locale' acima; visitantes escolhem via
    // cookie e usuários logados persistem a preferência na conta (users.locale).
    'available_locales' => array_values(array_filter(explode(',', (string) env('PLATFORM_AVAILABLE_LOCALES', 'pt_BR,en,es')))),

    // Timezone de EXIBIÇÃO (borda). Internamente tudo é UTC (ADR-010).
    'display_timezone' => env('PLATFORM_DISPLAY_TIMEZONE', 'America/Sao_Paulo'),

    // Moeda padrão da plataforma (multi-moeda preparado: valor + moeda — ADR-004).
    'currency' => env('PLATFORM_CURRENCY', 'BRL'),

];
