<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorização por SCOPE da chave de API (ADR-006 — menor privilégio).
 *
 * Uso em rotas (SEMPRE depois de resolve.tenant):
 *
 *   Route::post('/pix', ...)->middleware('scope:pix:create');
 *
 * O parâmetro é "recurso:acao" (ex.: customers:read, withdrawals:*).
 * A verificação é ApiKey::allows() — casamento exato ou wildcard.
 * Sem permissão: 403 com mensagem traduzida indicando o scope exigido.
 */
final class EnsureApiKeyScope
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $apiKey = $this->tenantContext->apiKey();

        if ($apiKey === null || ! $apiKey->allows($scope)) {
            abort(403, __('api_keys.scopes.denied', ['scope' => $scope]));
        }

        return $next($request);
    }
}
