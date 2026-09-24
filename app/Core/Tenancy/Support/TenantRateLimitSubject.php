<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Support;

use App\Core\Security\ApiRateLimit;
use App\Core\Security\Contracts\RateLimitSubjectResolver;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * O sujeito do limite da API numa requisição autenticada pelo
 * `resolve.tenant`: a CHAVE de API (padrão) ou o tenant dono dela
 * (RATE_LIMIT_API_BY=tenant). Fora de rota autenticada, null — e o
 * ApiRateLimit conta pelo IP.
 *
 * Registrado no container pelo TenancyServiceProvider.
 */
final class TenantRateLimitSubject implements RateLimitSubjectResolver
{
    public function __construct(private readonly TenantContext $context) {}

    public function resolve(Request $request): ?string
    {
        $apiKey = $this->context->apiKey();

        if (! $this->context->resolved() || $apiKey === null) {
            return null;
        }

        if (ApiRateLimit::countsByTenant()) {
            return 'tenant:'.(string) $this->context->user()?->uuid;
        }

        return 'key:'.(string) $apiKey->uuid;
    }
}
