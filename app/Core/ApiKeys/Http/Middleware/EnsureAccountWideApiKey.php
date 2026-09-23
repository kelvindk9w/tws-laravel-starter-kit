<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operação de CONTA exige chave de conta toda (ADR-005/006).
 *
 * Uma chave vinculada a projetos é, por definição, uma credencial de alcance
 * menor que a conta. Deixá-la fazer operação de conta anularia o vínculo:
 *
 *   - gerenciar chaves: a chave se desvincularia sozinha (vínculo vazio =
 *     conta toda), revogaria ou rotacionaria as chaves de conta, ou listaria
 *     as chaves e os projetos a que cada uma dá acesso;
 *   - criar projeto: produziria um projeto fora do seu próprio alcance, isto
 *     é, agiria sobre a conta e não sobre os projetos a que foi limitada.
 *
 * Por isso o vínculo LIMITA o scope: com a chave restrita, estas rotas
 * respondem 403 mesmo que os scopes dela (padrão `*:*`) as permitam.
 *
 * Exceção única, `account.key:self`: a chave vinculada pode agir sobre SI
 * MESMA (rotacionar-se ou revogar-se, com o {uuid} da rota igual ao dela).
 * Nenhuma das duas amplia acesso — a rotação herda scopes e restrição, e a
 * revogação só remove —, e são justamente o que se espera fazer com uma
 * credencial suspeita de vazamento. Vincular projetos a si mesma continua
 * proibido: lista vazia = conta toda.
 *
 * Uso (SEMPRE depois de resolve.tenant): `->middleware('account.key')` ou
 * `->middleware('account.key:self')`.
 */
final class EnsureAccountWideApiKey
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, ?string $allow = null): Response
    {
        $apiKey = $this->tenantContext->apiKey();

        if ($apiKey === null) {
            abort(403, __('api_keys.projects.account_key_required'));
        }

        if ($apiKey->isRestrictedToProjects() && ! $this->actsOnItself($request, $apiKey->uuid, $allow)) {
            abort(403, __('api_keys.projects.account_key_required'));
        }

        return $next($request);
    }

    private function actsOnItself(Request $request, mixed $ownUuid, ?string $allow): bool
    {
        if ($allow !== 'self') {
            return false;
        }

        $target = $request->route('uuid');

        return is_string($target) && is_string($ownUuid) && hash_equals($ownUuid, $target);
    }
}
