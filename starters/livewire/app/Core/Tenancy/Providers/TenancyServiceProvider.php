<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Providers;

use App\Core\Tenancy\Support\TenantRateLimitSubject;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Twstec\Kit\Foundation\Security\Contracts\RateLimitSubjectResolver;

/**
 * Serviços do módulo de Tenancy.
 *
 * - O contexto do tenant da requisição, preenchido pelo middleware
 *   ResolveTenant. PHP-FPM garante o ciclo por requisição; se Octane entrar
 *   um dia, resetar entre requisições (senão o tenant vaza de uma requisição
 *   para a outra).
 * - Quem o limite da API conta numa requisição autenticada: Segurança define
 *   o contrato, este módulo diz quem é o cliente (a chave ou o tenant).
 */
final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(RateLimitSubjectResolver::class, TenantRateLimitSubject::class);
    }
}
