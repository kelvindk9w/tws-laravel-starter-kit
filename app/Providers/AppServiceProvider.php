<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\ApiKeys\Console\ProcessApiKeyInactivity;
use App\Core\Auth\Console\MakeAdminUser;
use App\Core\Support\DemoSurface;
use App\Core\Support\Platform;
use App\Core\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            // APP_DEBUG em produção é vazamento de configuração, caminho de
            // servidor e trecho de código na tela de erro. O .env.example
            // entrega APP_DEBUG=true (é o arquivo de DESENVOLVIMENTO, e debug
            // desligado em dev só faz a pessoa ligar de volta), então o caminho
            // normal — copiar o exemplo e trocar o APP_ENV — chegaria em
            // produção com debug ligado.
            //
            // Aqui ele é FORÇADO a desligar, em vez de a aplicação recusar
            // subir: recusar o boot transformaria uma configuração errada num
            // site fora do ar, e o objetivo é fechar o vazamento, não derrubar
            // o deploy. O aviso no log é o que permite descobrir e corrigir a
            // origem. (O docker-compose.prod.yml já injeta APP_DEBUG=false;
            // isto cobre quem não sobe pelo compose do kit.)
            if (config('app.debug') === true) {
                config(['app.debug' => false]);

                Log::warning('APP_DEBUG estava ligado em APP_ENV=production e foi forçado para false. Corrija o ambiente: em produção o debug expõe configuração, caminhos do servidor e trechos de código nas telas de erro.');
            }

            // Opt-out da superfície de demonstração (DEMO_ALLOW_IN_PRODUCTION):
            // legítimo para a demo pública hospedada do roadmap, e por isso
            // BARULHENTO. Um opt-out de segurança que ninguém vê deixa de ser
            // decisão e volta a ser esquecimento.
            if (DemoSurface::allowedInProductionByOptOut()) {
                Log::warning('DEMO_ALLOW_IN_PRODUCTION está ligado: em produção, as contas demo de credenciais públicas, a vitrine /ui, a galeria /mail-preview e os seeders de dado fictício estão LIBERADOS. Só use isto numa instalação descartável.');
            }
        }

        // Comandos próprios do kit (fora de app/Console/Commands).
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessApiKeyInactivity::class,
                MakeAdminUser::class,
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
