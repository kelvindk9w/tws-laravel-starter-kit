<?php

declare(strict_types=1);

use App\Core\ApiKeys\Http\Middleware\EnsureApiKeyScope;
use App\Core\Auth\Http\Middleware\RequiresSensitiveActionToken;
use App\Core\Localization\Middleware\SetLocale;
use App\Core\Logging\Middleware\RequestLogging;
use App\Core\Security\Middleware\SecurityHeaders;
use App\Core\Security\Middleware\SecurityValidation;
use App\Core\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pipeline global — ADR-004/005, nesta ordem:
        // 1º SecurityHeaders (o mais externo: até respostas de bloqueio/erro
        //    carregam os headers de segurança);
        // 2º SecurityValidation (PRIMEIRA validação: rejeita payload malicioso
        //    antes de qualquer outro processamento, registrando a tentativa);
        // 3º RequestLogging (INICIADA imediato → CONCLUIDA/ERRO no terminate).
        //    Global de propósito, com guarda interna para api/*: middleware de
        //    grupo NÃO executa em rota não encontrada, e requisições para
        //    endpoints inexistentes são exatamente o sinal de varredura/ataque
        //    que o ADR-010 manda registrar.
        $middleware->prepend(RequestLogging::class);
        $middleware->prepend(SecurityValidation::class);
        $middleware->prepend(SecurityHeaders::class);

        // Cadeia da API: rate limiting global (valores em config/security.php).
        $middleware->api(prepend: ['throttle:api']);

        // Locale da interface web (ADR-007): usuário logado → preferência da
        // conta; visitante → cookie; fallback → padrão da plataforma (pt-BR).
        $middleware->web(append: [SetLocale::class]);

        // Aliases para uso explícito em rotas/grupos.
        $middleware->alias([
            'security.validation' => SecurityValidation::class,
            'security.headers' => SecurityHeaders::class,
            'request.logging' => RequestLogging::class,
            // Exige token de ação sensível válido (ADR-006) — uso único.
            'sensitive.token' => RequiresSensitiveActionToken::class,
            // Tenancy da API (ADR-010): resolve o tenant pela pk_/sk_ no
            // header, vincula o request log e atualiza o last_used_at.
            'resolve.tenant' => ResolveTenant::class,
            // Autorização por scope da chave (ADR-006): 'scope:recurso:acao'.
            'scope' => EnsureApiKeyScope::class,
        ]);

        // Deny-by-default (checklist 13): convidado em rota `auth` vai para
        // o login; `redirect()->intended()` devolve ao destino original.
        $middleware->redirectGuestsTo(fn (): string => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Captura a mensagem da exceção para o request log finalizar como ERRO
        // com o motivo (redigido depois pelo RequestLogging — LGPD).
        $exceptions->report(function (Throwable $e): void {
            $request = request();

            if (! $request->attributes->has('request_log_error')) {
                $request->attributes->set('request_log_error', $e->getMessage());
            }
        });
    })->create();
