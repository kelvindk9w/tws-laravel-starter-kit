<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * A REGRA do limite de requisições da API, em um lugar só.
 *
 * PROBLEMA: o `throttle:api` rodava ANTES do `resolve.tenant`. Nesse ponto
 * ninguém sabe ainda quem é o cliente, então o limite que se chamava "por
 * usuário" contava, na prática, sempre por IP. Dois efeitos ruins de uma vez:
 *
 *   INTEGRAÇÕES ATRÁS DO MESMO IP DIVIDIAM O ORÇAMENTO — dois clientes da
 *   API saindo pelo mesmo NAT (ou dois serviços do mesmo servidor) se
 *   derrubavam mutuamente, sem que nenhum dos dois tivesse passado do seu
 *   próprio limite.
 *
 *   O LIMITE NÃO ACOMPANHAVA A CREDENCIAL — uma integração que trocasse de
 *   saída de rede (vários workers, várias regiões) ganhava orçamento novo a
 *   cada IP, com a mesma chave.
 *
 * SOLUÇÃO EM DOIS BALDES, porque há duas perguntas diferentes:
 *
 *   1. REQUISIÇÃO AUTENTICADA → conta pela CHAVE DE API (ou pelo tenant,
 *      com RATE_LIMIT_API_BY=tenant). É o limiter nomeado `api`, aplicado
 *      pelo `throttle:api` do grupo da API — que agora roda DEPOIS do
 *      `resolve.tenant` (a ordem é garantida pela lista de prioridade de
 *      middleware em bootstrap/app.php, não pela posição na rota). Rota da
 *      API sem autenticação (`/api/health`) continua contando por IP.
 *
 *   2. FALHA DE AUTENTICAÇÃO → conta por IP (IPv6 por prefixo, o mesmo
 *      ClientBucket da borda). Mover o limite para depois da autenticação
 *      abre uma porta óbvia: a requisição com chave inválida é recusada com
 *      401 dentro do `resolve.tenant`, ANTES de chegar ao `throttle:api`.
 *      Sem este segundo balde, um laço de chaves inválidas só esbarraria no
 *      teto da borda (300/min por IP), que não foi feito para isso. Então o
 *      próprio ResolveTenant consulta este balde ANTES de ler a credencial e
 *      o alimenta a cada recusa — o padrão de throttle de login.
 *
 * O balde de falhas é consultado antes de verificar a credencial, inclusive
 * quando ela é válida: um IP que estourou o limite de tentativas inválidas
 * fica recusado até a janela passar, também para as chaves boas que saiam
 * dele. É a troca de sempre do throttle de login — sem ela, o atacante
 * intercalaria uma chave válida para "zerar" o balde —, e é o motivo de o
 * padrão ser folgado (erros de configuração de uma integração legítima são
 * poucos por minuto; um laço de adivinhação são centenas).
 *
 * O teto da borda (EdgeRateLimit, por IP) continua valendo por fora de
 * tudo isto: é ele que segura o volume bruto que chega ao PHP.
 *
 * Toda resposta 429 da API sai no envelope de erro padrão com Retry-After
 * (ApiErrorRenderer). Valores em config/security.php (`rate_limit.api*`).
 */
final class ApiRateLimit
{
    /**
     * Prefixo das chaves do balde de falhas de autenticação no cache.
     */
    private const AUTH_FAILURE_PREFIX = 'api-auth-failures:';

    /**
     * Limite do limiter nomeado `api` para esta requisição.
     */
    public static function limit(Request $request): Limit
    {
        return Limit::perMinute(max(1, (int) config('security.rate_limit.api', 60)))
            ->by(self::subject($request));
    }

    /**
     * QUEM está sendo contado: a chave (ou o tenant) quando a requisição foi
     * autenticada pelo `resolve.tenant`; o IP quando não foi.
     *
     * Os prefixos separam os espaços de nome — um IP nunca colide com o uuid
     * de uma chave.
     */
    public static function subject(Request $request): string
    {
        $context = app(TenantContext::class);
        $apiKey = $context->apiKey();

        if ($context->resolved() && $apiKey !== null) {
            if (self::countsByTenant()) {
                return 'tenant:'.(string) $context->user()?->uuid;
            }

            return 'key:'.(string) $apiKey->uuid;
        }

        return 'ip:'.ClientBucket::for($request);
    }

    /**
     * Recusa com 429 quando este cliente já errou a autenticação vezes
     * demais na janela. Chamado pelo ResolveTenant ANTES de ler a credencial.
     *
     * @throws ThrottleRequestsException
     */
    public static function ensureAuthenticationAllowed(Request $request): void
    {
        $key = self::authFailureKey($request);
        $max = self::maxAuthFailures();

        if (! RateLimiter::tooManyAttempts($key, $max)) {
            return;
        }

        $retryAfter = max(1, RateLimiter::availableIn($key));

        throw new ThrottleRequestsException(
            __('api.errors.too_many_requests'),
            null,
            [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => 0,
            ],
        );
    }

    /**
     * Conta uma falha de autenticação deste cliente. Na PRIMEIRA vez que ele
     * atinge o limite na janela, registra um evento no log — o sinal de
     * adivinhação de chave que a operação precisa ver, sem uma linha por
     * tentativa.
     */
    public static function recordAuthenticationFailure(Request $request): void
    {
        $key = self::authFailureKey($request);
        $max = self::maxAuthFailures();

        $hits = RateLimiter::hit($key, self::authFailureDecaySeconds());

        if ($hits === $max) {
            Log::channel('request_log')->warning('api.auth_failures.throttled', [
                'ip' => $request->ip(),
                'limit' => $max,
                'decay_seconds' => self::authFailureDecaySeconds(),
            ]);
        }
    }

    /**
     * O limite autenticado conta por tenant (em vez de por chave)?
     */
    public static function countsByTenant(): bool
    {
        return strtolower(trim((string) config('security.rate_limit.api_by', 'key'))) === 'tenant';
    }

    private static function authFailureKey(Request $request): string
    {
        return self::AUTH_FAILURE_PREFIX.ClientBucket::for($request);
    }

    private static function maxAuthFailures(): int
    {
        return max(1, (int) config('security.rate_limit.api_auth_failures', 20));
    }

    private static function authFailureDecaySeconds(): int
    {
        return max(1, (int) config('security.rate_limit.api_auth_failures_decay_seconds', 60));
    }
}
