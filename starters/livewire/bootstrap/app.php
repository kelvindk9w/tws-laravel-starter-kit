<?php

declare(strict_types=1);

use App\Core\ApiKeys\Http\Middleware\EnsureAccountWideApiKey;
use App\Core\ApiKeys\Http\Middleware\EnsureApiKeyScope;
use App\Core\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Twstec\Kit\Foundation\Http\Exceptions\ApiErrorRenderer;
use Twstec\Kit\Foundation\Localization\Middleware\SetLocale;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A PILHA GLOBAL DE SEGURANÇA (TrustProxies → SecurityHeaders →
        // EdgeRateLimit → TrustHosts → SecurityValidation → RequestLogging, na
        // frente de todo o resto) é instalada pelo pacote twstec/kit-foundation,
        // com a ordem e o porquê de cada posição documentados em
        // Twstec\Kit\Foundation\FoundationServiceProvider::GLOBAL_MIDDLEWARE.
        // Os aliases `security.validation`, `security.headers` e
        // `request.logging` também vêm de lá. Aqui fica só a composição do
        // aplicativo: API, prioridade, grupo web e os aliases dos outros módulos.

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
        // ResolveTenant limita as falhas por IP (Twstec\Kit\Foundation\Security\ApiRateLimit).
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: ResolveTenant::class,
        );

        // Locale da interface web: usuário logado → preferência da
        // conta; visitante → cookie; fallback → padrão da plataforma (pt-BR).
        //
        // O status da conta a cada requisição web (EnsureAccountIsActive) é
        // anexado ao FIM do grupo pelo pacote twstec/kit-auth — logo depois
        // deste SetLocale, para a mensagem de recusa sair no idioma da conta.
        // Conta bloqueada/pendente com sessão aberta perde a sessão na próxima
        // requisição — página, formulário ou ação Livewire (o endpoint do
        // Livewire está no grupo `web`).
        $middleware->web(append: [SetLocale::class]);

        // Aliases para uso explícito em rotas/grupos. Os de autenticação —
        // `sensitive.token` (token de ação sensível, uso único) e `verified`
        // (painel só com e-mail confirmado, AUTH_EMAIL_VERIFICATION_REQUIRED;
        // substitui o do framework) — são instalados pelo pacote
        // twstec/kit-auth.
        $middleware->alias([
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
