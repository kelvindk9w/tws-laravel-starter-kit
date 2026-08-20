<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Support\Platform;
use App\Core\Tenancy\TenantContext;

// =============================================================================
// Helpers globais do starter kit.
// Carregado via composer.json → autoload.files.
// =============================================================================

if (! function_exists('platform')) {
    /**
     * Acesso global tipado à configuração da plataforma (ADR-007).
     *
     * Ex.: platform()->name, platform()->officialUrl, platform()->supportEmail
     */
    function platform(): Platform
    {
        return app(Platform::class);
    }
}

if (! function_exists('tenant')) {
    /**
     * Tenant da requisição corrente (usuário dono da chave de API — ADR-010).
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
