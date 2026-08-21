<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Settings\SettingsManager;
use App\Core\Support\Platform;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\ViewErrorBag;

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

if (! function_exists('setting')) {
    /**
     * Valor efetivo de uma configuração editável pelo super admin
     * (tabela settings → fallback do .env/config). Somente chaves da
     * whitelist de config/settings.php.
     */
    function setting(string $key): mixed
    {
        return app(SettingsManager::class)->get($key);
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

if (! function_exists('form_error_display')) {
    /**
     * Estratégia efetiva de exibição de erros de validação nos formulários
     * clássicos (config/ui.php → error_display), com override por formulário.
     * Whitelist: inline | summary | toast | both (fora dela → 'inline').
     */
    function form_error_display(?string $override = null): string
    {
        $strategy = $override ?? (string) config('ui.error_display', 'inline');

        return in_array($strategy, ['inline', 'summary', 'toast', 'both'], true) ? $strategy : 'inline';
    }
}

if (! function_exists('field_error')) {
    /**
     * Erro inline de um campo, respeitando a estratégia configurada: retorna
     * '' (campo sem marcação) quando a estratégia é summary/toast — nesses
     * modos o erro aparece apenas no <x-form-errors>. Uso:
     * <x-input name="email" :error="field_error('email')" />.
     */
    function field_error(string $field, ?string $display = null): string
    {
        if (! in_array(form_error_display($display), ['inline', 'both'], true)) {
            return '';
        }

        /** @var ViewErrorBag $errors */
        $errors = view()->shared('errors');

        return $errors->first($field);
    }
}
