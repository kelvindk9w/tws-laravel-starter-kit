<?php

declare(strict_types=1);

namespace App\Core\Logging;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Assinatura do endpoint gravada na trilha de auditoria (ADR-004/005/010).
 *
 * PROBLEMA: o caminho REAL da URL é dado do usuário e pode carregar segredo
 * posicional. O link de recuperação de senha leva o token no PATH
 * (`/reset-password/{token}`) — gravar o caminho real colocaria o token em
 * claro no /admin, nos arquivos de log e, por tabela, nos backups. O banco
 * só guarda o HASH do token: o log viraria a via de tomada de conta.
 *
 * SOLUÇÃO: gravar o PADRÃO da rota (`reset-password/{token}`). O valor de
 * diagnóstico e observabilidade é o mesmo — na verdade melhor, porque as
 * chamadas de um mesmo endpoint passam a agrupar — sem nenhum segredo.
 *
 * Por que casar a rota aqui: os middlewares de log são GLOBAIS de propósito
 * (precisam registrar também as rotas inexistentes, que é o sinal de
 * varredura do ADR-010). Middleware global roda ANTES do roteamento, então
 * `$request->route()` ainda é nulo — a rota é casada contra a coleção sem
 * despachar nada. Se o middleware for usado por alias dentro de um grupo,
 * a rota já está resolvida e o casamento nem acontece.
 *
 * Sem rota casada (404, varredura, método não permitido) NÃO se cai de volta
 * no caminho bruto: um 404 de varredura carrega segredo no path do mesmo
 * jeito (basta o link de recuperação chegar depois da rota ser removida, ou
 * um typo no domínio). Grava-se um marcador explícito com a PROFUNDIDADE do
 * caminho — quantos segmentos tinha —, que distingue sondagem de raiz de
 * traversal profundo sem revelar um único caractere do que foi pedido.
 */
final class EndpointSignature
{
    /**
     * Profundidade máxima reportada no marcador (acima disso, satura).
     */
    private const MAX_DEPTH = 20;

    /**
     * Assinatura segura do endpoint da requisição.
     */
    public static function for(Request $request): string
    {
        $route = $request->route();

        if ($route instanceof Route && $route->uri() !== '') {
            return $route->uri();
        }

        $matched = self::matchWithoutDispatching($request);

        if ($matched !== null) {
            return $matched;
        }

        return self::unmatchedMarker($request);
    }

    /**
     * Casa a requisição contra a coleção de rotas sem despachá-la.
     * Qualquer falha (rota inexistente, método não permitido, coleção ainda
     * não carregada) devolve nulo — o log nunca pode derrubar a requisição.
     */
    private static function matchWithoutDispatching(Request $request): ?string
    {
        try {
            $route = app(Router::class)->getRoutes()->match($request);

            return $route->uri() !== '' ? $route->uri() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Marcador de rota não casada + profundidade do caminho pedido.
     */
    private static function unmatchedMarker(Request $request): string
    {
        /** @var string $marker */
        $marker = config('security.request_logging.unmatched_endpoint', '[unmatched]');

        $depth = min(
            count(array_filter(explode('/', trim($request->path(), '/')), fn (string $s): bool => $s !== '')),
            self::MAX_DEPTH,
        );

        return $marker.':'.$depth;
    }
}
