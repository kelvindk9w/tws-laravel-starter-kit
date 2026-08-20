<?php

declare(strict_types=1);

use App\Core\Logging\Middleware\RequestLogging;
use App\Core\Security\Middleware\SecurityHeaders;
use App\Core\Security\Middleware\SecurityValidation;
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

        // Aliases para uso explícito em rotas/grupos.
        $middleware->alias([
            'security.validation' => SecurityValidation::class,
            'security.headers' => SecurityHeaders::class,
            'request.logging' => RequestLogging::class,
        ]);
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
