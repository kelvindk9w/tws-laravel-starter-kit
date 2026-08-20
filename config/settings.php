<?php

declare(strict_types=1);

// =============================================================================
// Configurações editáveis pelo super admin pela UI (Fase 6 — ADR-006/007).
//
// WHITELIST (lei): somente as chaves abaixo podem ser editadas no painel
// /admin → Configurações. Cada chave mapeia para um caminho de config: um
// valor presente na tabela `settings` SOBRESCREVE em runtime o valor do .env
// (ver SettingsServiceProvider). Ausente na tabela = .env vigente.
//
// NUNCA adicionar aqui chaves de segredos/credenciais (APP_KEY, DB, mail...):
// a tabela settings não é cofre. Apenas parâmetros operacionais ajustáveis.
// =============================================================================

return [

    // Chave do cache das sobreposições (invalidada a cada escrita).
    'cache_key' => 'settings.db_overrides',

    // Chave da tabela settings => [caminho de config, tipo, limites de UI].
    // As labels das telas vivem em lang/pt_BR/admin.php (ADR-007).
    'overrides' => [
        // Expiração de chaves de API por inatividade (ADR-006): meses sem uso
        // até desativar + dias de aviso prévio por e-mail.
        'api_keys.inactivity.months' => ['type' => 'int', 'min' => 1, 'max' => 36],
        'api_keys.inactivity.warning_days' => ['type' => 'int', 'min' => 1, 'max' => 90],

        // Limites de upload (Fase 5 — checklist item 14).
        'uploads.types.image.max_kb' => ['type' => 'int', 'min' => 64, 'max' => 51200],
        'uploads.types.pdf.max_kb' => ['type' => 'int', 'min' => 64, 'max' => 102400],

        // Rate limits (checklist item 10): requisições por minuto.
        'security.rate_limit.api' => ['type' => 'int', 'min' => 1, 'max' => 10000],
        'security.rate_limit.sensitive' => ['type' => 'int', 'min' => 1, 'max' => 100],
    ],

];
