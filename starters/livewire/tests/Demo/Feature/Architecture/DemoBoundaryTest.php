<?php

declare(strict_types=1);

use App\Core\Auth\Contracts\AccountProtection;
use App\Core\Auth\Contracts\LoginPrefillProvider;
use App\Core\Auth\Models\User;
use App\Core\Mail\Contracts\MailPreviewGate;
use App\Demo\Accounts\DemoAccountProtection;
use App\Demo\Accounts\DemoLoginPrefill;
use App\Demo\Database\Seeders\DemoSeeder;
use App\Demo\Filament\Dashboards\ContentDashboard;
use App\Demo\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Demo\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use App\Demo\Filament\Resources\Products\Pages\CreateProduct;
use App\Demo\Filament\Resources\Products\Pages\EditProduct;
use App\Demo\Filament\Resources\Products\Pages\ListProducts;
use App\Demo\Filament\Widgets\Overview\LatestSubmissions;
use App\Demo\Support\DemoMailPreviewGate;
use App\Filament\Dashboards\OverviewDashboard;
use App\Filament\Support\AdminAudit;
use App\Filament\Support\BaseListRecords;
use App\Filament\Support\BaseResource;
use App\Filament\Widgets\Overview\LatestUploads;
use Database\Seeders\DatabaseSeeder;
use Filament\Resources\Resource;
use Illuminate\Support\Str;
use Livewire\Component;
use Symfony\Component\Finder\Finder;

// =============================================================================
// A FRONTEIRA DA DEMONSTRAÇÃO, vista do lado da demo.
//
// As regras de arquitetura do produto (AdminAuditTest, TableViewModeTest,
// FormSubmissionsTest, LocaleTest…) varrem os diretórios do produto — as
// telas, views e traduções da demo saíram de lá e ficariam sem trava. Aqui
// as MESMAS regras valem para app/Demo e demo/, e se confere que o provider
// da demo liga cada ponto de extensão do produto.
// =============================================================================

/**
 * Arquivos PHP das telas da demo no /admin (app/Demo/Filament).
 *
 * @return array<string, string> caminho relativo => conteúdo
 */
function demoFilamentSources(): array
{
    $sources = [];

    foreach ((new Finder)->files()->in(app_path('Demo/Filament'))->name('*.php') as $file) {
        $sources[str_replace(base_path().'/', '', $file->getRealPath())] = $file->getContents();
    }

    ksort($sources);

    return $sources;
}

/**
 * Classe declarada num arquivo de app/ (PSR-4 do App\).
 */
function demoClassOf(string $path): string
{
    return 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($path, 'app/'));
}

it('o provider da demo liga cada ponto de extensão do produto', function (): void {
    expect(app(AccountProtection::class))->toBeInstanceOf(DemoAccountProtection::class)
        ->and(app(LoginPrefillProvider::class))->toBeInstanceOf(DemoLoginPrefill::class)
        ->and(app(MailPreviewGate::class))->toBeInstanceOf(DemoMailPreviewGate::class)
        ->and(array_map(fn (object $seeder): string => $seeder::class, iterator_to_array(app()->tagged(DatabaseSeeder::EXTENSION_TAG))))->toBe([DemoSeeder::class])
        ->and(config('dashboards.variants.content.page'))->toBe(ContentDashboard::class)
        ->and(config('audit.admin_extension_namespaces'))->toContain('App\\Demo\\Filament\\')
        ->and(config('security.headers.surfaces.landing_alt'))->toBe(['v2'])
        ->and(app(OverviewDashboard::class)->getWidgets())->toContain(LatestSubmissions::class);

    $widgets = app(OverviewDashboard::class)->getWidgets();

    expect(array_search(LatestSubmissions::class, $widgets, true))
        ->toBe(array_search(LatestUploads::class, $widgets, true) - 1);
});

it('toda tela da demo no /admin está coberta pelo escopo de auditoria', function (): void {
    $componentes = [];

    foreach (array_keys(demoFilamentSources()) as $path) {
        $class = demoClassOf($path);

        if (class_exists($class) && is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $componentes[] = $class;

            expect(AdminAudit::covers($class))->toBeTrue("{$class} não passa pela trilha de auditoria");
        }
    }

    expect($componentes)->toContain(
        ListProducts::class,
        CreateProduct::class,
        EditProduct::class,
        ListFormSubmissions::class,
        ViewFormSubmission::class,
        ContentDashboard::class,
        LatestSubmissions::class,
    );
});

it('nenhuma escrita das telas da demo passa por fora dos eventos de model', function (): void {
    // As mesmas regras de AdminAuditTest, aplicadas a app/Demo/Filament.
    $proibidos = [
        '/\bDB::(?!transaction\()/' => 'DB:: (use o model; só DB::transaction é permitido)',
        '/Quietly\s*\(/' => '*Quietly() não dispara evento de model',
        '/withoutEvents\s*\(|withoutEventDispatcher/' => 'withoutEvents() desliga a captura',
        '/::truncate\s*\(|->truncate\s*\(/' => 'truncate()',
        '/->upsert\s*\(|::upsert\s*\(/' => 'upsert()',
        '/(?:->|::)insert(?:OrIgnore|GetId|Using)?\s*\(/' => 'insert() em massa',
        '/->(?:increment|decrement)(?:Each)?\s*\(/' => 'increment()/decrement() em massa',
    ];

    $violacoes = [];

    foreach (demoFilamentSources() as $path => $source) {
        foreach ($proibidos as $regex => $motivo) {
            if (preg_match($regex, $source) === 1) {
                $violacoes[] = "{$path}: {$motivo}";
            }
        }

        foreach (explode(';', $source) as $sentenca) {
            if (preg_match('/(?:::query\(\)|::where\w*\(|->where\w*\()/', $sentenca) === 1
                && preg_match('/->(?:update|delete|forceDelete)\s*\(/', $sentenca) === 1) {
                $violacoes[] = "{$path}: update/delete em massa numa consulta — ".trim(Str::limit($sentenca, 120));
            }
        }
    }

    expect($violacoes)->toBe([]);
});

it('toda recusa das telas da demo passa por AdminAudit::denied()', function (): void {
    $violacoes = [];

    foreach (demoFilamentSources() as $path => $source) {
        if (preg_match('/Notification::make\(\)[^;]*->danger\(\)/s', $source) === 1) {
            $violacoes[] = $path;
        }
    }

    expect($violacoes)->toBe([], 'Recusa montada à mão (Notification ...->danger()) não fica na trilha. Use AdminAudit::denied().');
});

it('todo resource da demo estende as bases do kit e traduz rótulo e grupo', function (): void {
    $resources = [];
    $listagens = [];

    foreach (array_keys(demoFilamentSources()) as $path) {
        $class = demoClassOf($path);

        if (! class_exists($class)) {
            continue;
        }

        if (is_subclass_of($class, Resource::class)) {
            $resources[] = $class;
        }

        if (str_contains($path, '/Pages/List')) {
            $listagens[] = $class;
        }
    }

    expect($resources)->not->toBeEmpty()
        ->and($listagens)->not->toBeEmpty();

    foreach ($resources as $class) {
        expect(is_subclass_of($class, BaseResource::class))->toBeTrue("{$class} precisa estender App\\Filament\\Support\\BaseResource")
            ->and($class::getModelLabel())->not->toContain('admin.')
            ->and($class::getPluralModelLabel())->not->toContain('admin.')
            ->and($class::getNavigationGroup())->not->toContain('admin.');
    }

    foreach ($listagens as $class) {
        expect(is_subclass_of($class, BaseListRecords::class))->toBeTrue("{$class} precisa estender App\\Filament\\Support\\BaseListRecords");
    }
});

it('as traduções da demo têm os mesmos arquivos e chaves nos três idiomas', function (): void {
    $reference = [];

    foreach (glob(base_path('demo/lang/pt_BR/*.php')) as $file) {
        $reference[basename($file)] = collect(require $file)->dot()->keys()->sort()->values();
    }

    expect($reference)->not->toBeEmpty();

    foreach (['en', 'es'] as $locale) {
        foreach ($reference as $file => $keys) {
            $path = base_path("demo/lang/{$locale}/{$file}");

            expect($path)->toBeFile();

            $actual = collect(require $path)->dot()->keys()->sort()->values();

            expect($actual->all())->toBe($keys->all(), "demo/lang/{$locale}/{$file} diverge do pt-BR");
        }
    }
});

it('nenhuma view da demo exibe submissão com echo cru ({!! !!})', function (): void {
    $views = iterator_to_array((new Finder)->files()->in(base_path('demo/resources/views'))->name('*.blade.php'));

    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        expect(preg_match('/\{!!.*(message|nickname|submission)/i', $view->getContents()))
            ->toBe(0, "Echo cru de campo de submissão em {$view->getRealPath()}");
    }
});

it('nenhuma tela do produto baixa os bundles das landings', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

    $bundles = array_map(
        fn (string $entrada): string => $manifest[$entrada]['file'],
        ['demo/resources/js/landing.js', 'demo/resources/css/landing.css', 'demo/resources/js/landing-v2.js', 'demo/resources/css/landing-v2.css'],
    );

    $painel = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

    foreach ($bundles as $bundle) {
        expect($painel)->not->toContain($bundle);
    }

    // E a landing, sim, baixa o dela — a verificação acima não é vazia.
    expect($this->get('/')->assertOk()->getContent())->toContain($manifest['demo/resources/js/landing.js']['file']);
});
