<?php

declare(strict_types=1);

namespace Twstec\Kit\Accounts\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twstec\Kit\Accounts\ApiKeys\Models\ApiKey;
use Twstec\Kit\Accounts\ApiKeys\Support\ApiKeyHasher;
use Twstec\Kit\Accounts\ApiKeys\Support\PepperMatch;
use Twstec\Kit\Accounts\Tenancy\TenantContext;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Foundation\Logging\Models\RequestLog;
use Twstec\Kit\Foundation\Security\ApiRateLimit;

/**
 * Resolve o TENANT a partir das credenciais de API no header.
 *
 * Par de credenciais (documentado em docs/api.md):
 * - `X-Api-Key: pk_live_...`        → chave PÚBLICA (lookup).
 * - `Authorization: Bearer sk_live_...` → chave SECRETA (verificação).
 *
 * Pipeline: existência da pk_ → verificação timing-safe da sk_ (hash_equals,
 * nunca comparação comum; pepper atual, anteriores e — só com a flag — o
 * vazio legado) → status/validade/grace/inatividade → usuário ativo →
 * registra o tenant no container (tenant()/tenantKey()) e no user resolver
 * da request → vincula o request log ao tenant (tenant_uuid = uuid do dono)
 * → regrava o hash com o pepper atual se conferiu com um anterior →
 * last_used_at throttled.
 *
 * Credencial inválida: 401 padronizado e o request log permanece SEM tenant
 * — exatamente o sinal de ataque/tentativa de burla que a trilha precisa mostrar
 * (o RequestLogging grava INICIADA antes desta camada, sem vincular tenant).
 */
final class ResolveTenant
{
    /**
     * Header da chave pública (a secreta vai no Authorization: Bearer).
     */
    public const PUBLIC_KEY_HEADER = 'X-Api-Key';

    /**
     * Evento da trilha de arquivo (canal request_log) quando o hash de uma
     * chave é regravado com o pepper atual.
     */
    public const HASH_MIGRATED_EVENT = 'api_keys.secret_hash.migrated';

    public const HASH_MIGRATION_FAILED_EVENT = 'api_keys.secret_hash.migration_failed';

    public function __construct(
        private readonly ApiKeyHasher $hasher,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Limite de FALHAS de autenticação (ver ApiRateLimit): por IP +
        // credencial, e um teto por IP que não barra chave já autenticada
        // daquele IP. Quem passou do limite recebe 429 antes de qualquer
        // consulta ao banco ou verificação de hash. É o que impede um laço de
        // chaves inválidas de escapar do limite da API — o `throttle:api` por
        // chave roda DEPOIS desta camada e nunca vê o 401.
        ApiRateLimit::ensureAuthenticationAllowed($request);

        $publicKey = $request->header(self::PUBLIC_KEY_HEADER);
        $plainSecret = $request->bearerToken();

        if (! is_string($publicKey) || $publicKey === '' || ! is_string($plainSecret) || $plainSecret === '') {
            $this->deny($request);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = ApiKey::query()->where('public_key', $publicKey)->first();

        // Timing-safe SEMPRE: verifica o hash mesmo quando a pk_ não existe
        // (segredo inválido contra hash fictício), para não vazar por tempo
        // de resposta se a chave pública é válida. O hash fictício usa o
        // mesmo pepper normalizado do hasher (nunca vazio), e o check() tenta
        // TODOS os peppers aceitos nos dois caminhos — o custo dos peppers
        // anteriores não vira sinal de "esta pk_ existe".
        $hashToVerify = $apiKey?->secret_hash
            ?? hash_hmac('sha256', 'chave-publica-inexistente', $this->hasher->currentPepper());

        $match = $this->hasher->check($plainSecret, (string) $hashToVerify);

        if (! $match->matched() || ! $apiKey instanceof ApiKey) {
            $this->deny($request);
        }

        if (! $apiKey->isUsable()) {
            $this->deny($request);
        }

        $tenant = $apiKey->owner;

        // Dono sem e-mail confirmado também não opera pela API (mesma regra do
        // painel — EmailVerification). Chave só nasce pelo painel, que já
        // exige a confirmação; isto cobre a conta que tinha chave antes de a
        // exigência ser ligada. Mesma recusa muda da conta inativa.
        if ($tenant === null || ! $tenant->isActive() || EmailVerification::pendingFor($tenant)) {
            $this->deny($request);
        }

        $this->tenantContext->resolve($tenant, $apiKey);

        ApiRateLimit::recordAuthenticationSuccess($request);

        // $request->user() e Auth::user() passam a ser o tenant nesta rota
        // (rate limiter por usuário, middleware sensitive.token, controllers).
        $request->setUserResolver(static fn () => $tenant);

        $this->bindRequestLog($request, (string) $tenant->uuid);

        if ($match->needsRehash()) {
            $this->migrateSecretHash($apiKey, $plainSecret, (string) $hashToVerify, $match, (string) $tenant->uuid);
        }

        $apiKey->touchLastUsedThrottled();

        return $next($request);
    }

    /**
     * 401 padronizado. O request log fica SEM tenant (sinal de ataque);
     * nenhum detalhe do motivo é exposto (não oracular). Toda
     * recusa alimenta os baldes de falhas (ApiRateLimit).
     */
    private function deny(Request $request): never
    {
        ApiRateLimit::recordAuthenticationFailure($request);

        abort(401, __('api_keys.auth.invalid'));
    }

    /**
     * Migração transparente no primeiro uso: a secreta conferiu com um pepper
     * anterior (ou com o vazio legado, com a flag ligada) e a autenticação
     * passou inteira — o hash é regravado com o pepper ATUAL, e o próximo uso
     * já confere direto.
     *
     * A escrita é condicional ao hash antigo (duas requisições simultâneas
     * não regravam duas vezes) e registra `api_keys.secret_hash.migrated` na
     * trilha de arquivo, sem segredo nem hash: só a chave, o dono e a origem.
     * Falha aqui NÃO derruba a requisição já autenticada — a migração é
     * tentada de novo no próximo uso.
     */
    private function migrateSecretHash(ApiKey $apiKey, string $plainSecret, string $oldHash, PepperMatch $match, string $tenantUuid): void
    {
        try {
            $newHash = $this->hasher->hash($plainSecret);

            $updated = ApiKey::query()
                ->whereKey($apiKey->getKey())
                ->where('secret_hash', $oldHash)
                ->update(['secret_hash' => $newHash]);

            if ($updated > 0) {
                $apiKey->setRawAttributes(['secret_hash' => $newHash] + $apiKey->getAttributes(), true);

                Log::channel('request_log')->notice(self::HASH_MIGRATED_EVENT, [
                    'api_key_uuid' => $apiKey->uuid,
                    'tenant_uuid' => $tenantUuid,
                    'from' => $match->value,
                ]);
            }
        } catch (Throwable $exception) {
            Log::channel('request_log')->warning(self::HASH_MIGRATION_FAILED_EVENT, [
                'api_key_uuid' => $apiKey->uuid,
                'tenant_uuid' => $tenantUuid,
                'from' => $match->value,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * Vincula o request log ao tenant. O log foi gravado como
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
