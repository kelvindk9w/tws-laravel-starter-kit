<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Twstec\Kit\Admin\Dashboards\OverviewDashboard;
use Twstec\Kit\Admin\Support\AdminAudit;
use Twstec\Kit\Admin\Widgets\Overview\LatestUploads;
use Twstec\Kit\Auth\Contracts\AccountProtection;
use Twstec\Kit\Auth\Contracts\LoginPrefillProvider;
use Twstec\Kit\Demo\Accounts\DemoAccountProtection;
use Twstec\Kit\Demo\Accounts\DemoLoginPrefill;
use Twstec\Kit\Demo\Filament\Dashboards\ContentDashboard;
use Twstec\Kit\Demo\Filament\DemoAdminPlugin;
use Twstec\Kit\Demo\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Twstec\Kit\Demo\Filament\Resources\Products\ProductResource;
use Twstec\Kit\Demo\Filament\Widgets\Overview\LatestSubmissions;
use Twstec\Kit\Demo\Support\DemoMailPreviewGate;
use Twstec\Kit\Demo\Tests\TestCase;
use Twstec\Kit\Foundation\Mail\Contracts\MailPreviewGate;
use Twstec\Kit\Foundation\Mail\MailPreview;

// =============================================================================
// FIAÇÃO DA DEMONSTRAÇÃO numa aplicação limpa: descoberta, pontos de extensão
// do produto, configuração (a do aplicativo vence), rotas, migrations e o
// plugin no /admin — tudo ligado pelo pacote, sem o aplicativo nomeá-lo.
// =============================================================================

it('é descoberta pelo Laravel com o provider que a suíte registra', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe(TestCase::PACKAGE_PROVIDERS)
        ->and($composer['name'])->toBe('twstec/kit-demo')
        ->and($composer['autoload']['psr-4'])->toBe(['Twstec\\Kit\\Demo\\' => 'src/'])
        // O e-mail de contato na galeria /mail-preview vem do autoload do
        // pacote — o aplicativo não o registra.
        ->and($composer['autoload']['files'])->toBe(['src/Contact/Mail/previews.php']);
});

it('responde pelos pontos de extensão do produto', function (): void {
    expect(app(AccountProtection::class))->toBeInstanceOf(DemoAccountProtection::class)
        ->and(app(LoginPrefillProvider::class))->toBeInstanceOf(DemoLoginPrefill::class)
        ->and(app(MailPreviewGate::class))->toBeInstanceOf(DemoMailPreviewGate::class)
        ->and(config('dashboards.variants.content.page'))->toBe(ContentDashboard::class)
        // Sem DASHBOARD_ENABLED declarado, a variante da demo entra no fim do padrão do produto.
        ->and(config('dashboards.enabled'))->toBe(['overview', 'growth', 'content'])
        ->and(config('dashboards.widgets.overview'))->toContain(['widget' => LatestSubmissions::class, 'before' => LatestUploads::class])
        ->and(config('audit.admin_extension_namespaces'))->toContain('Twstec\\Kit\\Demo\\Filament\\')
        ->and(config('security.headers.surfaces.landing_alt'))->toBe(['v2'])
        ->and(MailPreview::slugs())->toContain('contact-message');
});

it('põe as telas da demo no /admin pelo plugin, cobertas pela trilha de auditoria', function (): void {
    $panel = $this->panel();

    expect($panel->hasPlugin(DemoAdminPlugin::make()->getId()))->toBeTrue()
        ->and($panel->getResources())->toContain(ProductResource::class, FormSubmissionResource::class)
        ->and(AdminAudit::covers(ContentDashboard::class))->toBeTrue()
        ->and(app(OverviewDashboard::class)->getWidgets())->toContain(LatestSubmissions::class);
});

it('registra as rotas da demo com os nomes de sempre', function (): void {
    foreach (['landing', 'landing.v2', 'contact.store', 'ui.showcase', 'ui.form-demo'] as $name) {
        expect(Route::has($name))->toBeTrue("rota {$name} ausente");
    }

    $this->get('/v3')->assertRedirect('/')->assertStatus(301);
});

it('traz as migrations dela, com os mesmos nomes de arquivo de antes do pacote', function (): void {
    $migrations = array_map('basename', glob(dirname(__DIR__, 2).'/database/migrations/*.php'));

    // Os nomes são os que já estão gravados na tabela `migrations` de quem
    // rodou a demo quando ela morava no aplicativo: mudar um deles faria a
    // migration rodar de novo.
    expect($migrations)->toBe([
        '2026_08_21_000005_create_products_table.php',
        '2026_08_21_000006_create_form_submissions_table.php',
        '2026_09_04_000001_add_sender_email_to_form_submissions_table.php',
        '2026_09_05_000001_add_ip_to_form_submissions_table.php',
        '2026_09_05_000002_protect_demo_users_with_trigger.php',
        '2026_09_23_000001_protect_demo_users_from_truncate.php',
        '2026_09_24_000003_protect_demo_users_two_factor_column.php',
    ])
        ->and(app('migrator')->paths())->toContain(dirname(__DIR__, 2).'/database/migrations');

    $this->artisan('migrate:status')->expectsOutputToContain('2026_08_21_000005_create_products_table')->assertSuccessful();
});

it('traz a própria configuração (landing e flags da demo) e a do aplicativo vence', function (): void {
    expect(config('ui.demo_login.email'))->toBe('demo@tws.dev')
        ->and(config('ui.demo_admin.email'))->toBe('admin@tws.dev')
        ->and(config('ui.demo.allow_in_production'))->toBeFalse()
        ->and(config('landing'))->toBeArray()->not->toBeEmpty();

    $this->bootWith(['ui.demo_login.email' => 'outra@exemplo.com']);

    expect(config('ui.demo_login.email'))->toBe('outra@exemplo.com')
        // Chave que o aplicativo não mexeu: a do pacote continua.
        ->and(config('ui.demo_admin.email'))->toBe('admin@tws.dev');
});

it('sobe sem os pontos de extensão que são do starter (links do site, agregador de seeders)', function (): void {
    // Esta aplicação não tem App\Livewire\Support\SiteLinks nem
    // Database\Seeders\DatabaseSeeder: a demo liga o resto e não quebra.
    expect(class_exists('App\\Livewire\\Support\\SiteLinks'))->toBeFalse()
        ->and(class_exists('Database\\Seeders\\DatabaseSeeder'))->toBeFalse()
        ->and(app()->tagged('database.seeders'))->toBeEmpty()
        ->and(Route::has('landing'))->toBeTrue();
});
