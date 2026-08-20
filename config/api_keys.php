<?php

declare(strict_types=1);

// =============================================================================
// Motor de API Keys + Tenancy (Fase 4 — ADR-005/006/010).
//
// Todos os valores são ajustáveis por .env — NUNCA hardcodar (ADR-007).
// =============================================================================

return [

    // Ambiente das chaves geradas: define o prefixo pk_live_/sk_live_ ou
    // pk_test_/sk_test_. Produção usa 'live'; sandbox/dev usam 'test'.
    'environment' => env('API_KEYS_ENVIRONMENT', 'live'),

    // --- Hash da chave secreta (checklist item 5) ------------------------------
    // DECISÃO (documentada no README): HMAC-SHA256 com pepper, no padrão Sanctum
    // (SHA-256). A sk_ tem 256 bits de entropia aleatória criptográfica — KDF
    // lenta (Argon2id) protege segredos de BAIXA entropia (senhas humanas); aqui
    // só adicionaria latência por requisição sem ganho. O pepper (segredo fora
    // do banco) garante que um vazamento SÓ do banco não permita computar nem
    // verificar hashes. A comparação é SEMPRE timing-safe via hash_equals().
    // Pepper próprio recomendado (API_KEYS_HASH_PEPPER); fallback: APP_KEY.
    'hash_pepper' => env('API_KEYS_HASH_PEPPER', env('APP_KEY')),

    // Atualização do last_used_at é throttled: no máximo 1 escrita a cada N
    // segundos por chave (a request nunca paga um UPDATE a cada chamada).
    'last_used_throttle_seconds' => (int) env('API_KEYS_LAST_USED_THROTTLE_SECONDS', 60),

    // --- Expiração por inatividade (ADR-006) -----------------------------------
    // Job diário (api-keys:process-inactivity): chave sem uso por `months`
    // meses é desativada (status expired_inactivity); `warning_days` antes,
    // envia e-mail de AVISO PRÉVIO (uma única vez por ciclo de inatividade).
    'inactivity' => [
        'enabled' => env('API_KEYS_INACTIVITY_ENABLED', true),
        'months' => (int) env('API_KEYS_INACTIVITY_MONTHS', 3),
        'warning_days' => (int) env('API_KEYS_INACTIVITY_WARNING_DAYS', 7),
    ],

    // --- Rotação (ADR-006) -------------------------------------------------------
    // No ato da rotação o usuário escolhe a morte da antiga: grace_period_minutes
    // nulo/0 = morte imediata; positivo = janela de coexistência (sem downtime).
    'rotation' => [
        'max_grace_minutes' => (int) env('API_KEYS_MAX_GRACE_MINUTES', 10080),
    ],

    // Paginação das listagens da API v1.
    'pagination' => [
        'per_page' => (int) env('API_KEYS_PER_PAGE', 15),
    ],

    // Scopes padrão na criação: TUDO habilitado (ADR-006). O usuário pode
    // restringir por recurso:ação (menor privilégio) informando `scopes`.
    'default_scopes' => ['*:*'],

];
