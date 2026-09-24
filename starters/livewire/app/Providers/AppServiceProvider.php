<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\MakeAdminUser;
use App\Livewire\Support\SiteLinks;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Twstec\Kit\Auth\Http\Middleware\EnsureEmailIsVerified;
use Twstec\Kit\Foundation\Security\Middleware\EnsureAdminIpAllowed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O singleton da plataforma (platform()) e a troca do `backup:run` pela
        // versão que recusa backup sem criptografia em produção são do pacote
        // twstec/kit-foundation (FoundationServiceProvider). O contexto do
        // tenant da requisição e o comando `api-keys:process-inactivity` são
        // do pacote twstec/kit-accounts (AccountsServiceProvider).

        // Links do site acrescentados por extensões (cabeçalho e rodapé
        // públicos — ver SiteLinks). Um por aplicação: nada vaza entre testes.
        $this->app->singleton(SiteLinks::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // As guardas de produção da base do kit (segredo crítico, HTTPS,
        // APP_DEBUG e os avisos dos opt-outs de segurança) são aplicadas pelo
        // pacote twstec/kit-foundation, sozinho — ver
        // Twstec\Kit\Foundation\Support\ProductionHardening.

        // Downloads de export/import do Filament (`/filament/exports/…`): o
        // pacote registra esse grupo com `['web']` apenas, fora do painel — ou
        // seja, o arquivo gerado A PARTIR do /admin (tabela de usuários, logs de
        // requisição) era entregue por uma rota que a barreira de origem do
        // painel não cobria. Mesma superfície, mesma barreira. O `push` mantém
        // o que o pacote declarar amanhã, em vez de congelar a lista dele.
        Route::pushMiddlewareToGroup('filament.actions', EnsureAdminIpAllowed::class);

        // Verificação de e-mail nas AÇÕES Livewire do painel. O endpoint de
        // atualização do Livewire é um só para todos os componentes e não
        // carrega os middlewares da rota da página; só reaplica os da lista
        // de persistentes, com a rota de origem gravada no snapshot. Sem esta
        // linha, a página /api-keys mandaria ao aviso, mas a ação "criar
        // chave" disparada de um snapshot anterior passaria.
        Livewire::addPersistentMiddleware([EnsureEmailIsVerified::class]);

        // Comandos próprios do kit (fora de app/Console/Commands).
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAdminUser::class,
            ]);
        }

        // Os limitadores `api` e `sensitive` são registrados pelo pacote
        // twstec/kit-foundation (FoundationServiceProvider). Um RateLimiter::for
        // com o mesmo nome aqui substituiria o do pacote.
    }
}
