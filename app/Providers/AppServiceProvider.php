<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Support\Platform;
use App\Core\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configuração centralizada da plataforma (ADR-007) — singleton tipado.
        $this->app->singleton(Platform::class, fn (): Platform => Platform::fromConfig());

        // Contexto do tenant da requisição (ADR-010) — preenchido pelo
        // middleware ResolveTenant. PHP-FPM garante o ciclo por requisição;
        // se Octane entrar um dia, resetar entre requisições (checklist 28).
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HTTPS forçado em produção (checklist item 3). O redirect 80→443 e o
        // HSTS na borda são do nginx; aqui garantimos que TODA URL gerada
        // pela aplicação (e-mails, webhooks, links assinados) saia em https.
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // Comandos próprios do kit (fora de app/Console/Commands).
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Core\ApiKeys\Console\ProcessApiKeyInactivity::class,
            ]);
        }

        // Rate limiting (checklist item 10) — valores via config/security.php.
        // Chave: usuário autenticado quando houver; caso contrário, IP.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limit.api', 60))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        // Rotas sensíveis (login, códigos 2FA/verificação, recuperação de senha):
        // Route::middleware('throttle:sensitive').
        RateLimiter::for('sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limit.sensitive', 5))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });
    }
}
