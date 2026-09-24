<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Tenancy\TenantContext;
use App\Models\User;

// =============================================================================
// Helpers globais do módulo de Tenancy.
// Carregado via composer.json → autoload.files.
// =============================================================================

if (! function_exists('tenant')) {
    /**
     * Tenant da requisição corrente (usuário dono da chave de API).
     * Null fora de rotas protegidas pelo middleware resolve.tenant.
     */
    function tenant(): ?User
    {
        return app(TenantContext::class)->user();
    }
}

if (! function_exists('tenantKey')) {
    /**
     * Chave de API que autenticou a requisição corrente (null fora de rotas
     * com resolve.tenant). Útil para scopes e vínculos chave↔projeto.
     */
    function tenantKey(): ?ApiKey
    {
        return app(TenantContext::class)->apiKey();
    }
}
