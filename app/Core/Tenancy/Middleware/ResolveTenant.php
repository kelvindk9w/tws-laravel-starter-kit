<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Middleware;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Support\ApiKeyHasher;
use App\Core\Logging\Models\RequestLog;
use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Resolve o TENANT a partir das credenciais de API no header (ADR-010).
 *
 * Par de credenciais (documentado no README):
 * - `X-Api-Key: pk_live_...`        → chave PÚBLICA (lookup).
 * - `Authorization: Bearer sk_live_...` → chave SECRETA (verificação).
 *
 * Pipeline: existência da pk_ → verificação timing-safe da sk_ (hash_equals,
 * checklist item 5) → status/validade/grace/inatividade → usuário ativo →
 * registra o tenant no container (tenant()/tenantKey()) e no user resolver
 * da request → vincula o request log ao tenant (tenant_uuid = uuid do dono)
 * → last_used_at throttled.
 *
 * Credencial inválida: 401 padronizado e o request log permanece SEM tenant
 * — exatamente o sinal de ataque/tentativa de burla definido no ADR-010
 * (o RequestLogging grava INICIADA antes desta camada, sem vincular tenant).
 */
final class ResolveTenant
{
    /**
     * Header da chave pública (a secreta vai no Authorization: Bearer).
     */
    public const PUBLIC_KEY_HEADER = 'X-Api-Key';

    public function __construct(
        private readonly ApiKeyHasher $hasher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicKey = $request->header(self::PUBLIC_KEY_HEADER);
        $plainSecret = $request->bearerToken();

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($plainSecret) || $plainSecret === '') {
            $this->deny();
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = ApiKey::query()->where('public_key', $publicKey)->first();

        // Timing-safe SEMPRE: verifica o hash mesmo quando a pk_ não existe
        // (segredo inválido contra hash fictício), para não vazar por tempo
        // de resposta se a chave pública é válida.
        $hashToVerify = $apiKey?->secret_hash
            ?? hash_hmac('sha256', 'chave-publica-inexistente', (string) config('api_keys.hash_pepper'));

        if (! $this->hasher->verify($plainSecret, $hashToVerify) || ! $apiKey instanceof ApiKey) {
            $this->deny();
        }

        if (! $apiKey->isUsable()) {
            $this->deny();
        }

        $tenant = $apiKey->owner;

        if ($tenant === null || ! $tenant->isActive()) {
            $this->deny();
        }

        $this->tenantContext->resolve($tenant, $apiKey);

        // $request->user() e Auth::user() passam a ser o tenant nesta rota
        // (rate limiter por usuário, middleware sensitive.token, controllers).
        $request->setUserResolver(static fn () => $tenant);

        $this->bindRequestLog($request, (string) $tenant->uuid);

        $apiKey->touchLastUsedThrottled();

        return $next($request);
    }

    /**
     * 401 padronizado. O request log fica SEM tenant (sinal de ataque —
     * ADR-010); nenhum detalhe do motivo é exposto (não oracular).
     */
    private function deny(): never
    {
        abort(401, __('api_keys.auth.invalid'));
    }

    /**
     * Vincula o request log ao tenant (ADR-010). O log foi gravado como
     * INICIADA pelo RequestLogging (camada anterior); aqui ganha o
     * tenant_uuid do dono da chave. Falha de persistência NÃO derruba a
     * requisição — segue na trilha de arquivo (canal request_log).
     */
    private function bindRequestLog(Request $request, string $tenantUuid): void
    {
        try {
            $log = $request->attributes->get('request_log');

            if ($log instanceof RequestLog) {
                $log->bindTenant($tenantUuid);
            }
        } catch (Throwable $exception) {
            Log::channel('request_log')->warning('request.tenant_bind_failed', [
                'tenant_uuid' => $tenantUuid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
