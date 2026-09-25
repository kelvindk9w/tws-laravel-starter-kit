<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo;

use App\Livewire\Support\SiteLinks;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory as ViewFactory;
use Livewire\Livewire;
use Twstec\Kit\Admin\Widgets\Overview\LatestUploads;
use Twstec\Kit\Auth\Contracts\AccountProtection;
use Twstec\Kit\Auth\Contracts\LoginPrefillProvider;
use Twstec\Kit\Demo\Accounts\DemoAccountProtection;
use Twstec\Kit\Demo\Accounts\DemoAccountSession;
use Twstec\Kit\Demo\Accounts\DemoLoginPrefill;
use Twstec\Kit\Demo\Console\UninstallDemo;
use Twstec\Kit\Demo\Database\Seeders\DemoSeeder;
use Twstec\Kit\Demo\Filament\Dashboards\ContentDashboard;
use Twstec\Kit\Demo\Filament\DemoAdminPlugin;
use Twstec\Kit\Demo\Filament\Widgets\Overview\LatestSubmissions;
use Twstec\Kit\Demo\Livewire\ContactForm;
use Twstec\Kit\Demo\Support\DemoMailPreviewGate;
use Twstec\Kit\Demo\Support\DemoSurface;
use Twstec\Kit\Foundation\Localization\PackageTranslations;
use Twstec\Kit\Foundation\Mail\Contracts\MailPreviewGate;

/**
 * A DEMONSTRAÇÃO do kit (twstec/kit-demo), ligada ao produto pela descoberta
 * automática de pacotes do Laravel — e só por ela.
 *
 * Tudo o que existe para mostrar o kit — e não para ser a base de um produto
 * — mora neste pacote: as landings "Céu" (/) e "O Rastro" (/v2), a vitrine
 * /ui, o formulário de contato, o catálogo de produtos de exemplo, a caixa de
 * submissões e o dashboard "Conteúdo & Operação" do /admin, a proteção das
 * contas demo, os seeders de dado fictício, as imagens, o JS e o CSS das
 * landings.
 *
 * O starter declara o pacote em `require-dev`: está no ambiente de
 * desenvolvimento de quem clona o kit e NUNCA na imagem de produção
 * (`composer install --no-dev`). Tirar a demo é `composer remove --dev
 * twstec/kit-demo` (ver docs/demo.md).
 *
 * O produto não importa nada daqui. Onde ele precisava perguntar algo à demo,
 * pergunta a um ponto de extensão neutro, e este provider registra as
 * respostas da demo:
 *
 *   AccountProtection     → contas demo protegidas contra alteração;
 *   LoginPrefillProvider  → credenciais demo nas telas de login;
 *   MailPreviewGate       → /mail-preview segue o modo demo;
 *   SiteLinks             → âncoras da landing, /ui e contato no site;
 *   DatabaseSeeder (tag)  → o DemoSeeder roda no `db:seed`;
 *   config de dashboards  → variante "content" e as últimas submissões;
 *   config de auditoria   → as telas da demo no /admin entram na trilha;
 *   config de segurança   → CSP da landing alternativa em /v2;
 *   plugin do /admin      → produtos, submissões e o dashboard da demo.
 *
 * SiteLinks e o agregador de seeders são do STARTER (o aplicativo), não de um
 * pacote: a demo é a demonstração daquele aplicativo. Numa aplicação que não
 * os tem (a suíte isolada deste pacote), as duas ligações simplesmente não
 * acontecem.
 */
final class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configuração da landing (`landing`) e as flags da demo no grupo de
        // sempre (`ui.demo`, `ui.showcase_enabled`, `ui.demo_login`,
        // `ui.demo_admin`): as chaves lidas continuam as mesmas, e numa mesma
        // chave o config/ do aplicativo vence.
        $this->mergeConfigFrom($this->path('config/landing.php'), 'landing');
        $this->mergeConfigFrom($this->path('config/ui.php'), 'ui');

        // Pontos de extensão do produto, com as respostas da demo.
        $this->app->singleton(AccountProtection::class, DemoAccountProtection::class);
        $this->app->singleton(LoginPrefillProvider::class, DemoLoginPrefill::class);
        $this->app->bind(MailPreviewGate::class, DemoMailPreviewGate::class);

        // Gatilho das contas demo alinhado com o modo demo DESTA aplicação:
        // com o modo desligado, cada conexão com o PostgreSQL desliga a
        // proteção na própria sessão (ver DemoAccountSession). Fica no
        // register, e não no boot, para valer também para conexões abertas
        // por outros providers durante o boot. Não abre conexão nenhuma.
        DemoAccountSession::register($this->app['events'], $this->app['db']);

        // `db:seed`: o agregador do aplicativo roda os seeders marcados.
        if (class_exists(DatabaseSeeder::class)) {
            $this->app->tag([DemoSeeder::class], DatabaseSeeder::EXTENSION_TAG);
        }

        // Telas da demo no /admin (plugin do Filament aplicado a todo painel).
        Panel::configureUsing(static function (Panel $panel): void {
            $panel->plugin(DemoAdminPlugin::make());
        });

        // Dashboards: a variante "Conteúdo & Operação" e as últimas
        // submissões na Visão geral, antes dos últimos uploads.
        $this->setConfigIfMissing('dashboards.variants.content', [
            'page' => ContentDashboard::class,
            'icon' => 'heroicon-o-rectangle-stack',
            'sort' => 3,
        ]);
        // E a liga: sem DASHBOARD_ENABLED declarado, o padrão do produto
        // (`overview,growth`) ganha a variante da demo no fim. Declarado, vale
        // o que o operador escreveu — tirar `content` da lista tira a variante.
        if (Env::get('DASHBOARD_ENABLED') === null) {
            $this->appendConfigOnce('dashboards.enabled', 'content');
        }
        $this->appendConfigOnce('dashboards.widgets.overview', [
            'widget' => LatestSubmissions::class,
            'before' => LatestUploads::class,
        ]);

        // As telas da demo no /admin abrem o escopo de auditoria como as do
        // produto (AdminAudit::covers).
        $this->appendConfigOnce('audit.admin_extension_namespaces', 'Twstec\\Kit\\Demo\\Filament\\');

        // A landing alternativa (/v2) tem CSP própria: só o host de dados do
        // GitHub a mais no connect-src (security.headers.*landing_alt*).
        $this->appendConfigOnce('security.headers.surfaces.landing_alt', 'v2');

        $this->loadMigrationsFrom($this->path('database/migrations'));

        // Traduções: o aplicativo vence (PackageTranslations). Registradas
        // depois de todos os providers para ficarem mais perto do aplicativo
        // que as dos outros pacotes do kit: a demo dá o texto "de demo" às
        // mensagens neutras do produto sobre conta protegida (a conta demo é
        // a conta protegida daqui), e sem ela vale o texto neutro.
        $this->app->booting(function (): void {
            PackageTranslations::register($this->app, $this->path('lang'));
        });

        // Views sem namespace (`landing`, `showcase`, <x-layouts.landing>…),
        // como eram no aplicativo: a pasta entra DEPOIS da resources/views do
        // aplicativo, que vence numa view de mesmo nome.
        $this->callAfterResolving('view', function (ViewFactory $view): void {
            $view->addLocation($this->path('resources/views'));
        });
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group($this->path('routes/web.php'));
        }

        // Componente fora de App\Livewire: registrado pelo nome que as views
        // usam (<livewire:contact-form />).
        Livewire::component('contact-form', ContactForm::class);

        if (class_exists(SiteLinks::class)) {
            $this->registerSiteLinks($this->app->make(SiteLinks::class));
        }

        // `demo:uninstall`: tira do banco os gatilhos das contas demo (e, com
        // --drop-tables, as tabelas da demo) antes de o pacote sair.
        if ($this->app->runningInConsole()) {
            $this->commands([UninstallDemo::class]);
        }

        // Opt-out da superfície de demonstração (DEMO_ALLOW_IN_PRODUCTION):
        // legítimo para a demo pública hospedada do roadmap, e por isso
        // BARULHENTO. Um opt-out de segurança que ninguém vê deixa de ser
        // decisão e volta a ser esquecimento.
        if ($this->app->isProduction() && DemoSurface::allowedInProductionByOptOut()) {
            Log::warning('DEMO_ALLOW_IN_PRODUCTION está ligado: em produção, as contas demo de credenciais públicas, a vitrine /ui, a galeria /mail-preview e os seeders de dado fictício estão LIBERADOS. Só use isto numa instalação descartável.');
        }
    }

    /**
     * Links da demo no cabeçalho (âncoras da landing e a vitrine) e no rodapé
     * (vitrine, demo e contato — antes do status da API, que é do produto).
     */
    private function registerSiteLinks(SiteLinks $links): void
    {
        $links->add(SiteLinks::HEADER, 10, static fn (): array => ['label' => __('landing.nav.features'), 'href' => url('/#recursos')]);
        $links->add(SiteLinks::HEADER, 20, static fn (): array => ['label' => __('landing.nav.hours'), 'href' => url('/#horas')]);
        $links->add(SiteLinks::HEADER, 30, static fn (): array => ['label' => __('landing.nav.stack'), 'href' => url('/#stack')]);
        $links->add(SiteLinks::HEADER, 40, static fn (): array => ['label' => __('landing.nav.components'), 'href' => route('ui.showcase')]);

        $links->add(SiteLinks::FOOTER, 10, static fn (): array => ['label' => __('landing.footer.showcase'), 'href' => route('ui.showcase')]);
        $links->add(SiteLinks::FOOTER, 20, static fn (): array => ['label' => __('landing.footer.demo'), 'href' => route('login')]);
        $links->add(SiteLinks::FOOTER, 30, static fn (): array => ['label' => __('landing.footer.contact'), 'href' => url('/#contato')]);
    }

    /**
     * Grava a chave só se ela ainda não existir (o config em cache já a traz).
     */
    private function setConfigIfMissing(string $key, mixed $value): void
    {
        if (! $this->app['config']->has($key)) {
            $this->app['config']->set($key, $value);
        }
    }

    /**
     * Acrescenta o valor à lista da chave uma vez só (o config em cache já o
     * traz, e o register roda de novo a cada boot).
     */
    private function appendConfigOnce(string $key, mixed $value): void
    {
        $list = (array) $this->app['config']->get($key, []);

        if (! in_array($value, $list, true)) {
            $list[] = $value;
            $this->app['config']->set($key, $list);
        }
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
