<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\ApiKeys\Console\ProcessApiKeyInactivity;
use App\Core\Auth\Console\MakeAdminUser;
use App\Core\Backup\Console\GuardedBackupCommand;
use App\Core\Http\TrustedProxies;
use App\Core\Security\AdminIpAllowlist;
use App\Core\Security\ApiRateLimit;
use App\Core\Security\Middleware\EnsureAdminIpAllowed;
use App\Core\Support\CriticalSecrets;
use App\Core\Support\DemoSurface;
use App\Core\Support\Platform;
use App\Core\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Commands\BackupCommand;

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

        // `backup:run` com a regra da criptografia na frente: em produção,
        // backup que sairia sem criptografia é RECUSADO (ver
        // App\Core\Backup\BackupEncryption). O pacote resolve o comando pelo
        // container, então trocar a classe aqui cobre o comando manual, o
        // agendamento e o Artisan::call(). Nada disso roda no boot.
        $this->app->bind(BackupCommand::class, GuardedBackupCommand::class);
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
            // Segredos que não podem ser inventados (ver CriticalSecrets). A
            // chave da aplicação RECUSA o boot quando este processo vai servir
            // tráfego ou processar trabalho, e AVISA em voz alta nos comandos
            // de instalação e manutenção — sem `.env`, o Laravel resolve
            // APP_ENV como `production`, e uma recusa larga derrubaria o
            // `composer install`. Segredo de infraestrutura com valor de
            // fachada sempre avisa, nunca recusa. Toda essa decisão mora no
            // guard, não aqui.
            CriticalSecrets::guard();

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

            // Opt-out da barreira de ORIGEM das superfícies administrativas
            // (ADMIN_ALLOW_ANY_IP, ou faixa universal escrita na própria
            // allowlist). Mesmo princípio do aviso acima: sem allowlist, o
            // /admin e o /horizon ficam com UMA barreira só (is_admin + conta
            // ativa), e quem decidiu isso tem de reencontrar a decisão no log.
            //
            // Note que NÃO existe aviso de boot para o caso da allowlist
            // AUSENTE: sem `.env`, o Laravel resolve APP_ENV como `production`,
            // então um aviso ali sairia em todo `composer install` do CI, de
            // todo build de imagem e de todo primeiro clone — ruído que ensina
            // a ignorar avisos. Esse caso quem relata é o próprio middleware,
            // no instante em que recusa uma requisição real, onde a mensagem é
            // sempre verdadeira e sempre acionável.
            if (AdminIpAllowlist::anyIpAllowedInProductionByOptOut()) {
                Log::warning('A allowlist de IP do /admin e do /horizon está DESLIGADA por decisão explícita (ADMIN_ALLOW_ANY_IP, ou faixa universal na própria lista): as superfícies administrativas aceitam qualquer origem e contam apenas com is_admin + conta ativa. Isto só é seguro se a restrição de origem estiver na borda (VPN, Cloudflare Access, WAF, regra de firewall). Ver App\Core\Security\AdminIpAllowlist.');
            }

            // Opt-out de QUEM PODE DIZER QUEM É O CLIENTE (TRUSTED_PROXIES=*).
            // Mesmo princípio: confiar em qualquer origem para reescrever o IP
            // deixa a allowlist do admin, o rate limiting e a trilha de
            // auditoria à mercê de um header que o cliente escolhe. Existe
            // instalação em que é a resposta honesta (origem fechada por
            // firewall no ASN da CDN), e é por isso que é opt-out e não recusa —
            // mas ele tem de ser reencontrável no log.
            if (TrustedProxies::trustsEverythingInProduction()) {
                Log::warning('TRUSTED_PROXIES=* está declarado: a aplicação obedece X-Forwarded-For de QUALQUER origem, então o IP que a allowlist do /admin compara, o que agrupa o rate limiting e o que a trilha de auditoria grava são o que o cliente disser. Isto só é seguro se nada além do proxy alcançar a aplicação (firewall na origem). Estreite a lista para as faixas do seu proxy. Ver App\Core\Http\TrustedProxies.');
            }

            // O CONSERTO INTUITIVO ERRADO, reconhecido pela própria aplicação:
            // IP de proxy confiável escrito na allowlist do admin. Quem faz isso
            // está reagindo ao 403 que aparece atrás de um load balancer, e o
            // resultado é uma barreira que aceita a internet inteira com cara de
            // configurada — o único jeito de descobrir sozinho seria notar que
            // TODA requisição casa. Por isso o aviso nomeia os endereços.
            if (AdminIpAllowlist::proxyEntriesInProduction()) {
                Log::warning(sprintf(
                    'A allowlist do /admin contém endereços que são proxies confiáveis (%s): como TODA requisição chega com o endereço do proxy, a barreira de origem aceita qualquer visitante — configurada na aparência, inexistente na prática. Remova esses endereços de ADMIN_ALLOWED_IPS e liste os IPs de quem ADMINISTRA. Ver App\Core\Security\AdminIpAllowlist.',
                    implode(', ', AdminIpAllowlist::proxyEntries()),
                ));
            }
        }

        // Downloads de export/import do Filament (`/filament/exports/…`): o
        // pacote registra esse grupo com `['web']` apenas, fora do painel — ou
        // seja, o arquivo gerado A PARTIR do /admin (tabela de usuários, logs de
        // requisição) era entregue por uma rota que a barreira de origem do
        // painel não cobria. Mesma superfície, mesma barreira. O `push` mantém
        // o que o pacote declarar amanhã, em vez de congelar a lista dele.
        Route::pushMiddlewareToGroup('filament.actions', EnsureAdminIpAllowed::class);

        // Comandos próprios do kit (fora de app/Console/Commands).
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessApiKeyInactivity::class,
                MakeAdminUser::class,
            ]);
        }

        // Rate limiting (checklist item 10) — valores via config/security.php.
        // API: conta pela CHAVE de API (ou pelo tenant, RATE_LIMIT_API_BY)
        // quando a requisição foi autenticada, e por IP quando não foi. As
        // falhas de autenticação têm balde próprio por IP. A regra inteira
        // mora em App\Core\Security\ApiRateLimit.
        RateLimiter::for('api', fn (Request $request): Limit => ApiRateLimit::limit($request));

        // Rotas sensíveis (login, códigos 2FA/verificação, recuperação de senha):
        // Route::middleware('throttle:sensitive').
        RateLimiter::for('sensitive', function (Request $request): Limit {
            return Limit::perMinute((int) config('security.rate_limit.sensitive', 5))
                ->by((string) ($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });
    }
}
