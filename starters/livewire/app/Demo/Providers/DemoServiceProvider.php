<?php

declare(strict_types=1);

namespace App\Demo\Providers;

use App\Demo\Accounts\DemoAccountProtection;
use App\Demo\Accounts\DemoAccountSession;
use App\Demo\Accounts\DemoLoginPrefill;
use App\Demo\Database\Seeders\DemoSeeder;
use App\Demo\Filament\Dashboards\ContentDashboard;
use App\Demo\Filament\DemoAdminPlugin;
use App\Demo\Filament\Widgets\Overview\LatestSubmissions;
use App\Demo\Livewire\ContactForm;
use App\Demo\Support\DemoMailPreviewGate;
use App\Demo\Support\DemoSurface;
use App\Filament\Widgets\Overview\LatestUploads;
use App\Livewire\Support\SiteLinks;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory as ViewFactory;
use Livewire\Livewire;
use Twstec\Kit\Auth\Contracts\AccountProtection;
use Twstec\Kit\Auth\Contracts\LoginPrefillProvider;
use Twstec\Kit\Foundation\Mail\Contracts\MailPreviewGate;

/**
 * A DEMONSTRAÇÃO do kit, ligada ao produto num ponto só.
 *
 * Tudo o que existe para mostrar o kit — e não para ser a base de um produto
 * — mora em App\Demo (classes) e em demo/ (rotas, config, migrations, views,
 * traduções, JS e CSS): as landings "Céu" (/) e "O Rastro" (/v2), a vitrine
 * /ui, o formulário de contato, o catálogo de produtos de exemplo, a caixa de
 * submissões e o dashboard "Conteúdo & Operação" do /admin, a proteção das
 * contas demo e os seeders de dado fictício.
 *
 * O produto não importa nada daqui. Onde ele precisava perguntar algo à demo,
 * agora pergunta a um ponto de extensão neutro, e este provider registra as
 * respostas da demo:
 *
 *   AccountProtection     → contas demo protegidas contra alteração;
 *   LoginPrefillProvider  → credenciais demo nas telas de login;
 *   MailPreviewGate       → /mail-preview segue o modo demo;
 *   SiteLinks             → âncoras da landing, /ui e contato no site;
 *   DatabaseSeeder (tag)  → o DemoSeeder roda no `db:seed`;
 *   config de dashboards  → variante "content" e as últimas submissões;
 *   config de auditoria   → as telas da demo no /admin entram na trilha;
 *   config de segurança   → CSP da landing alternativa em /v2.
 *
 * DESLIGAR A DEMO: tirar este provider de bootstrap/providers.php e a linha
 * `app/Demo/Contact/Mail/previews.php` do `autoload.files` do composer.json
 * (o registro do e-mail de contato na galeria — ver o relatório da F1a para o
 * motivo de ser por autoload). O produto sobe e funciona sem nada disto.
 *
 * ARQUIVOS DA DEMO QUE AINDA MORAM FORA DE demo/ — presos por caminho em
 * testes existentes, que não podem mudar nesta fase; saem junto com os testes
 * da demo quando ela deixar o repositório:
 *   - resources/views/landing-v2.blade.php e resources/views/landing-v2/
 *     (LandingV2Test lê os arquivos por caminho);
 *   - lang/{locale}/landing.php (LandingTest compara os três idiomas por
 *     caminho; o cabeçalho e o rodapé do produto também usam chaves dele);
 *   - lang/{locale}/showcase.php (componentes do produto — <x-snippet>,
 *     <x-skeleton> — usam chaves dele);
 *   - config/ui.php: as chaves `demo`, `showcase_enabled`, `demo_login` e
 *     `demo_admin` (DemoLoginTest confere os padrões no arquivo);
 *   - resources/views/components/layouts/landing.blade.php, o apelido do
 *     layout público usado pela vitrine (DesignSystemTest o lê por caminho);
 *   - public/img/landing/ (LandingTest confere os arquivos por caminho).
 */
final class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Configuração da landing, no grupo de sempre (`landing`): as chaves
        // lidas continuam as mesmas. (As flags da demo continuam em
        // config/ui.php — ver a lista de arquivos presos no topo.)
        $this->mergeConfigFrom(base_path('demo/config/landing.php'), 'landing');

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

        // `db:seed`: o agregador do produto roda os seeders marcados.
        $this->app->tag([DemoSeeder::class], DatabaseSeeder::EXTENSION_TAG);

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
        $this->appendConfigOnce('dashboards.widgets.overview', [
            'widget' => LatestSubmissions::class,
            'before' => LatestUploads::class,
        ]);

        // As telas da demo no /admin abrem o escopo de auditoria como as do
        // produto (AdminAudit::covers).
        $this->appendConfigOnce('audit.admin_extension_namespaces', 'App\\Demo\\Filament\\');

        // A landing alternativa (/v2) tem CSP própria: só o host de dados do
        // GitHub a mais no connect-src (security.headers.*landing_alt*).
        $this->appendConfigOnce('security.headers.surfaces.landing_alt', 'v2');

        $this->loadMigrationsFrom(base_path('demo/database/migrations'));
        $this->loadTranslationsFrom(base_path('demo/lang'));
        $this->callAfterResolving('view', static function (ViewFactory $view): void {
            $view->addLocation(base_path('demo/resources/views'));
        });
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('demo/routes/web.php'));
        }

        // Componente fora de App\Livewire: registrado pelo nome que as views
        // usam (<livewire:contact-form />).
        Livewire::component('contact-form', ContactForm::class);

        $this->registerSiteLinks($this->app->make(SiteLinks::class));

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
}
