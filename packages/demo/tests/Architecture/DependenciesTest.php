<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// =============================================================================
// ARQUITETURA DO PACOTE demo — o topo do kit. Conhece os cinco pacotes do kit
// (foundation, auth, accounts, uploads, admin), o Laravel, o Filament, o
// Livewire e o Faker (dos seeders de dado fictício). Nada do kit depende dele:
// a trava do outro lado (nenhum arquivo do produto nomeia Twstec\Kit\Demo)
// mora no starter (tests/Unit/Architecture/ModuleDependenciesTest.php).
//
// A demo é a demonstração DO STARTER, e por isso pode tocar no aplicativo —
// mas só por uma lista FECHADA de pontos que são dele: o model de usuário
// (App\Models\User, dos seeders das contas demo), os links do site
// (App\Livewire\Support\SiteLinks) e o agregador do `db:seed`
// (Database\Seeders\DatabaseSeeder). Qualquer outro App\… reprova. Este
// arquivo reprova o build quando:
//
// 1. um arquivo do pacote (código, config, rotas, migrations, traduções) nomeia
//    classe do aplicativo fora da lista, ou o nome antigo da demo (App\Demo);
// 2. um `use` ou nome totalmente qualificado sai do que o pacote pode usar;
// 3. o composer.json declara algo além disso.
//
// A leitura do PHP é por tokens: comentários e strings não contam.
// =============================================================================

const DEMO_FORBIDDEN_PREFIXES = [
    'App\\',
    'Database\\',
];

/**
 * Pontos do APLICATIVO (o starter) que a demo pode tocar — e só estes.
 */
const DEMO_STARTER_EXTENSION_POINTS = [
    'App\\Models\\User',
    'App\\Livewire\\Support\\SiteLinks',
    'Database\\Seeders\\DatabaseSeeder',
];

const DEMO_ALLOWED_ROOTS = [
    'Twstec\\Kit\\Demo\\',
    'Twstec\\Kit\\Admin\\',
    'Twstec\\Kit\\Uploads\\',
    'Twstec\\Kit\\Accounts\\',
    'Twstec\\Kit\\Auth\\',
    'Twstec\\Kit\\Foundation\\',
    'Illuminate\\',
    'Filament\\',
    'Livewire\\',
    'Symfony\\Component\\HttpFoundation\\',
    'Symfony\\Component\\Finder\\',
    'Carbon\\',
    'Faker\\',
    'Ramsey\\Uuid\\',
];

const DEMO_SHIPPED_DIRECTORIES = ['src', 'config', 'database', 'lang', 'routes'];

function demoRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Nomes de classe de um arquivo PHP, lidos dos tokens: todos os qualificados
 * (`$all`) e só os que são certamente absolutos — os dos `use` do topo e os
 * escritos com `\` na frente (`$absolute`).
 *
 * @return array{all: list<string>, absolute: list<string>}
 */
function demoClassNamesIn(string $contents): array
{
    $all = [];
    $absolute = [];
    $inUse = false;
    $depth = 0;

    foreach (PhpToken::tokenize($contents) as $token) {
        if ($token->text === '{') {
            $depth++;
        } elseif ($token->text === '}') {
            $depth--;
        }

        if ($token->is(T_USE) && $depth === 0) {
            $inUse = true;

            continue;
        }

        if ($inUse && $token->text === ';') {
            $inUse = false;
        }

        if (! $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            continue;
        }

        $name = ltrim($token->text, '\\');
        $all[$name] = true;

        if ($inUse || $token->is(T_NAME_FULLY_QUALIFIED)) {
            $absolute[$name] = true;
        }
    }

    return ['all' => array_keys($all), 'absolute' => array_keys($absolute)];
}

/**
 * @return array<string, list<string>> caminho relativo => violações
 */
function demoDependencyViolations(): array
{
    $violations = [];

    foreach (DEMO_SHIPPED_DIRECTORIES as $directory) {
        foreach ((new Finder)->files()->in(demoRoot().'/'.$directory)->name('*.php') as $file) {
            $path = str_replace(demoRoot().'/', '', $file->getRealPath());
            $names = demoClassNamesIn($file->getContents());

            foreach ($names['all'] as $name) {
                if (in_array($name, DEMO_STARTER_EXTENSION_POINTS, true)) {
                    continue;
                }

                foreach (DEMO_FORBIDDEN_PREFIXES as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $violations[$path][] = "usa {$name}";
                    }
                }
            }

            foreach ($names['absolute'] as $name) {
                $allowed = false;

                foreach (DEMO_ALLOWED_ROOTS as $root) {
                    $allowed = $allowed || str_starts_with($name.'\\', $root);
                }

                $allowed = $allowed || in_array($name, DEMO_STARTER_EXTENSION_POINTS, true);

                // Funções e constantes globais importadas não têm `\`.
                if (! $allowed && str_contains($name, '\\')) {
                    $violations[$path][] = "usa {$name} (fora dos pacotes do kit + Laravel + Filament + Livewire + Faker e dos pontos do starter)";
                }
            }
        }
    }

    ksort($violations);

    return $violations;
}

it('não nomeia nada do aplicativo além dos pontos de extensão do starter', function (): void {
    $violations = [];

    foreach (demoDependencyViolations() as $path => $problems) {
        foreach (array_unique($problems) as $problem) {
            $violations[] = "{$path} {$problem}";
        }
    }

    expect($violations)->toBe([]);
});

it('as views do pacote não nomeiam classe do aplicativo (os componentes Blade do starter, sim)', function (): void {
    // As views da demo são páginas DO STARTER (landing, vitrine): usam os
    // componentes Blade dele (<x-button>, <x-layouts.site>…) — é isso que
    // elas demonstram. Classe PHP do aplicativo escrita numa view, não.
    $violations = [];

    foreach ((new Finder)->files()->in(demoRoot().'/resources/views')->name('*.blade.php') as $file) {
        if (preg_match('/App\\\\+(?!Models\\\\+User\\b)/', $file->getContents()) === 1) {
            $violations[] = str_replace(demoRoot().'/', '', $file->getRealPath());
        }
    }

    expect($violations)->toBe([]);
});

it('declara no composer.json as dependências que usa — os cinco pacotes do kit, o Laravel, o Filament, o Livewire e o Faker', function (): void {
    $composer = json_decode((string) file_get_contents(demoRoot().'/composer.json'), true);

    expect(array_keys($composer['require']))->toBe([
        'php',
        'fakerphp/faker',
        'filament/filament',
        'laravel/framework',
        'livewire/livewire',
        'twstec/kit-accounts',
        'twstec/kit-admin',
        'twstec/kit-auth',
        'twstec/kit-foundation',
        'twstec/kit-uploads',
    ]);
});

it('a leitura por tokens pega o que deve pegar (a trava não é cega)', function (): void {
    $sample = <<<'PHP'
        <?php
        namespace Twstec\Kit\Admin\Algo;
        use App\Models\User;
        use Twstec\Kit\Uploads\Models\Upload;
        use Spatie\Coisa\Qualquer;
        // App\Demo\Coisa em comentário não conta
        $a = new \Filament\Panel;
        $b = Support\AdminAudit::class;
        $c = 'App\\Filament\\Em\\String';
        PHP;

    $names = demoClassNamesIn($sample);

    expect($names['absolute'])->toBe(['App\Models\User', 'Twstec\Kit\Uploads\Models\Upload', 'Spatie\Coisa\Qualquer', 'Filament\Panel'])
        ->and($names['all'])->toContain('Support\AdminAudit')
        ->and($names['all'])->not->toContain('App\Demo\Coisa')
        ->and($names['all'])->not->toContain('App\Filament\Em\String');
});
