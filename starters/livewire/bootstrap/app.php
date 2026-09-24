<?php

declare(strict_types=1);

use App\Core\ApiKeys\Http\Middleware\EnsureAccountWideApiKey;
use App\Core\ApiKeys\Http\Middleware\EnsureApiKeyScope;
use App\Core\Auth\Http\Middleware\EnsureAccountIsActive;
use App\Core\Auth\Http\Middleware\EnsureEmailIsVerified;
use App\Core\Auth\Http\Middleware\RequiresSensitiveActionToken;
use App\Core\Http\Exceptions\ApiErrorRenderer;
use App\Core\Http\Middleware\TrustHosts;
use App\Core\Http\Middleware\TrustProxies;
use App\Core\Localization\Middleware\SetLocale;
use App\Core\Logging\Middleware\RequestLogging;
use App\Core\Security\Middleware\EdgeRateLimit;
use App\Core\Security\Middleware\SecurityHeaders;
use App\Core\Security\Middleware\SecurityValidation;
use App\Core\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pipeline global, nesta ordem:
        // 0º TrustProxies (quem pode dizer QUEM É O CLIENTE — ver abaixo);
        // 1º SecurityHeaders (até respostas de bloqueio/erro carregam os
        //    headers de segurança — inclusive o 400 de host recusado);
        // 1ºb EdgeRateLimit (TETO de requisições por cliente — IP ou prefixo
        //    IPv6 — para TUDO que chega ao PHP: páginas, Livewire, /admin, /up,
        //    API e rotas inexistentes). Vem depois do TrustProxies (precisa do
        //    IP real) e do SecurityHeaders (o 429 sai com os headers), e ANTES
        //    de todo trabalho caro: host, varredura de ataque sobre o corpo e
        //    INSERT/UPDATE na trilha. Acima do limite o corpo nem é lido. Ver
        //    App\Core\Security\Middleware\EdgeRateLimit;
        // 1ºc TrustHosts (quais valores de `Host` são aceitos — ver abaixo);
        // 2º SecurityValidation (PRIMEIRA validação de payload: rejeita
        //    conteúdo malicioso antes de qualquer outro processamento,
        //    registrando a tentativa);
        // 3º RequestLogging (INICIADA imediato → CONCLUIDA/ERRO no terminate).
        //    Global de propósito: middleware de grupo NÃO executa em rota não
        //    encontrada, e requisições para endpoints inexistentes são exatamente
        //    o sinal de varredura/ataque que precisa ficar registrado. Cobre
        //    API + web autenticada + /admin; exclusões e resumos (assets,
        //    health checks, updates Livewire) em config/security.php.
        //
        // TrustProxies e TrustHosts entram JUNTAS de propósito: declarar proxy
        // confiável é o que faz o `X-Forwarded-Host` (e, atrás de CDN, o próprio
        // `Host`) virar open redirect real — a validação de host é o que tranca
        // o que passava por ali. Toda a regra e a justificativa moram em
        // App\Core\Http\TrustedProxies e App\Core\Http\TrustedHosts.
        //
        // TrustProxies É A MAIS EXTERNA DE TODAS. Na posição padrão do
        // framework ela rodaria DEPOIS dos middlewares do kit, e aí o `ip()` que
        // o RequestLogging grava na trilha de auditoria e o que o
        // SecurityValidation registra numa tentativa de ataque ainda seriam o
        // endereço do PROXY. Ela também precisa vir antes do TrustHosts, porque
        // é ela que decide se um `X-Forwarded-Host` entra no host validado, e
        // antes do SecurityHeaders, que só envia HSTS sob HTTPS detectado.
        //
        // TrustHosts vem logo depois do SecurityHeaders (para o 400 sair com os
        // headers) e ANTES de tudo que lê o host — SecurityValidation,
        // RequestLogging, sessão, rotas: a requisição com host forjado para ali.
        $middleware->prepend(RequestLogging::class);
        $middleware->prepend(SecurityValidation::class);
        $middleware->prepend(TrustHosts::class);
        $middleware->prepend(EdgeRateLimit::class);
        $middleware->prepend(SecurityHeaders::class);
        $middleware->prepend(TrustProxies::class);

        // As duas são subclasses do middleware do framework porque a
        // declaração precisa ser LIDA DE CONFIGURAÇÃO, e este closure roda antes
        // de a configuração existir (`config()` aqui estoura) — por isso não se
        // usa `trustProxies(at:)`/`trustHosts(at:)`. Ver o docblock de
        // App\Core\Http\Middleware\TrustProxies. O `replace` impede que a
        // versão do framework rode também (ela está na lista global padrão).
        $middleware->replace(Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);

        // Cadeia da API: rate limiting (valores em config/security.php).
        $middleware->api(prepend: ['throttle:api']);

        // O `throttle:api` conta pela CHAVE de API, então precisa rodar DEPOIS
        // do `resolve.tenant`, que é quem descobre a chave. Pela posição ele
        // rodaria antes (middleware de grupo vem antes do de rota) e contaria
        // sempre por IP — era o defeito. A lista de PRIORIDADE do framework é
        // o que reordena middleware entre grupo e rota, e o ThrottleRequests
        // já está nela: pôr o ResolveTenant logo antes dele garante a ordem
        // em toda rota que usar os dois, sem depender de como a rota foi
        // declarada. Chave inválida não escapa por ficar antes: o próprio
        // ResolveTenant limita as falhas por IP (App\Core\Security\ApiRateLimit).
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: ResolveTenant::class,
        );

        // Locale da interface web: usuário logado → preferência da
        // conta; visitante → cookie; fallback → padrão da plataforma (pt-BR).
        //
        // Status da conta a cada requisição web (depois do SetLocale, para a
        // mensagem sair no idioma da conta): conta bloqueada/pendente com
        // sessão aberta perde a sessão na próxima requisição — página,
        // formulário ou ação Livewire (o endpoint do Livewire está no grupo
        // `web`). Ver EnsureAccountIsActive.
        $middleware->web(append: [SetLocale::class, EnsureAccountIsActive::class]);

        // Aliases para uso explícito em rotas/grupos.
        $middleware->alias([
            'security.validation' => SecurityValidation::class,
            'security.headers' => SecurityHeaders::class,
            'request.logging' => RequestLogging::class,
            // Exige token de ação sensível válido — uso único.
            'sensitive.token' => RequiresSensitiveActionToken::class,
            // Painel só com e-mail confirmado (AUTH_EMAIL_VERIFICATION_REQUIRED).
            // Substitui o `verified` do framework — ver EnsureEmailIsVerified.
            'verified' => EnsureEmailIsVerified::class,
            // Tenancy da API: resolve o tenant pela pk_/sk_ no
            // header, vincula o request log e atualiza o last_used_at.
            'resolve.tenant' => ResolveTenant::class,
            // Autorização por scope da chave: 'scope:recurso:acao'.
            'scope' => EnsureApiKeyScope::class,
            // Operação de conta (gerenciar chaves, criar projeto): recusa a
            // chave vinculada a projetos.
            'account.key' => EnsureAccountWideApiKey::class,
        ]);

        // Deny-by-default: convidado em rota `auth` vai para
        // o login; `redirect()->intended()` devolve ao destino original.
        $middleware->redirectGuestsTo(fn (): string => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Envelope padronizado de erro da API (`api/*`), contrapartida do
        // envelope de sucesso {"data": …} — ver ApiErrorRenderer e
        // docs/api.md. Nunca stack trace/caminho de servidor, nem com
        // APP_DEBUG=true: o contrato do cliente é o mesmo em todo ambiente.
        $exceptions->render(fn (Throwable $e, Request $request) => app(ApiErrorRenderer::class)($e, $request));

        // Captura a mensagem da exceção para o request log finalizar como ERRO
        // com o motivo (redigido depois pelo RequestLogging — LGPD).
        $exceptions->report(function (Throwable $e): void {
            $request = request();

            if (! $request->attributes->has('request_log_error')) {
                $request->attributes->set('request_log_error', $e->getMessage());
            }
        });
    })->create();
