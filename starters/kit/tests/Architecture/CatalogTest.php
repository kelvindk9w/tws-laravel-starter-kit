<?php

declare(strict_types=1);

use Twstec\Kit\Foundation\Kit;
use Twstec\Kit\Setup\Modules;

// =============================================================================
// O comando único roda ANTES de qualquer pacote do kit existir no projeto, e
// por isso repete o catálogo dos módulos e conhece os starters pelo nome.
// Estas travas garantem que nada disso diverge do monorepo — e que o pacote
// continua pequeno (o create-project baixa só ele e o Laravel Prompts).
// =============================================================================

$root = dirname(__DIR__, 2);
$monorepo = dirname($root, 2);

it('o catálogo dos módulos é o mesmo do foundation (Kit)', function () use ($monorepo): void {
    require_once $monorepo.'/packages/foundation/src/Kit.php';

    expect(Modules::REQUIRED)->toBe(Kit::REQUIRED)
        ->and(Modules::OPTIONAL)->toBe(Kit::OPTIONAL)
        ->and(Modules::DEPENDS_ON)->toBe(Kit::DEPENDS_ON)
        ->and(Modules::PACKAGES)->toBe(array_map(static fn (array $module): string => $module['package'], Kit::MODULES));
})->skip(! is_file($monorepo.'/packages/foundation/src/Kit.php'), 'fora do monorepo');

it('os starters do menu são os do monorepo: projetos com todos os pacotes do kit e o instalador', function () use ($root, $monorepo): void {
    $config = json_decode((string) file_get_contents($root.'/composer.json'), true)['extra']['twstec-kit'];

    expect(array_keys($config['starters']))->toBe(['livewire', 'react'])
        ->and($config['starters'])->toHaveKey($config['default-starter']);

    foreach ($config['starters'] as $stack => $package) {
        $starter = json_decode((string) file_get_contents("{$monorepo}/starters/{$stack}/composer.json"), true);

        expect($starter['name'])->toBe($package)
            ->and($starter['type'])->toBe('project')
            ->and($starter['require'])->toHaveKeys(array_values(Modules::PACKAGES))
            ->and($starter['require-dev'])->toHaveKey('twstec/kit-installer')
            // O comando único roda os mesmos passos do create-project do
            // starter (e o instalador lê a escolha do ambiente).
            ->and($starter['scripts'])->toHaveKeys(['post-root-package-install', 'post-create-project-cmd'])
            ->and(implode("\n", $starter['scripts']['post-create-project-cmd']))->toContain('tws:install');
    }
})->skip(! is_dir($monorepo.'/starters/livewire'), 'fora do monorepo');

it('depende só do PHP e do Laravel Prompts, e o create-project chama o kit-setup', function () use ($root): void {
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

    expect($composer['name'])->toBe('twstec/kit')
        ->and($composer['type'])->toBe('project')
        ->and(array_keys($composer['require']))->toBe(['php', 'laravel/prompts'])
        ->and($composer['scripts']['post-create-project-cmd'])->toBe(['Composer\\Config::disableProcessTimeout', '@php kit-setup/create.php']);
});

it('o código usa só o próprio pacote, o Laravel Prompts e o Symfony Console', function () use ($root): void {
    $allowed = ['Twstec\\Kit\\Setup', 'Laravel\\Prompts\\', 'Symfony\\Component\\Console\\'];
    $violations = [];

    foreach ([...glob($root.'/kit-setup/src/*.php'), ...glob($root.'/kit-setup/src/*/*.php'), $root.'/kit-setup/create.php'] as $file) {
        foreach (PhpToken::tokenize((string) file_get_contents($file)) as $token) {
            if (! $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $name = ltrim($token->text, '\\');
            $ok = str_starts_with($name, 'Contracts\\');

            foreach ($allowed as $prefix) {
                $ok = $ok || str_starts_with($name, $prefix);
            }

            if (! $ok) {
                $violations[] = basename($file).' usa '.$name;
            }
        }
    }

    expect(array_values(array_unique($violations)))->toBe([]);
});

it('o create.php carrega TODAS as classes antes de começar (o composer update troca o autoload em disco)', function () use ($root): void {
    $entry = (string) file_get_contents($root.'/kit-setup/create.php');

    foreach ([...glob($root.'/kit-setup/src/*.php'), ...glob($root.'/kit-setup/src/*/*.php')] as $file) {
        $relative = substr($file, strlen($root.'/kit-setup/src/'), -4);
        // A classe do menu só roda antes do update (e o carrega junto).
        if ($relative === 'Menu') {
            continue;
        }

        expect($entry)->toContain("'Twstec\\\\Kit\\\\Setup\\\\".str_replace('/', '\\\\', $relative)."'");
    }
});

it('o Docker de desenvolvimento (nome, número, portas) é o MESMO código no twstec/kit e no instalador', function () use ($root, $monorepo): void {
    $kit = glob($root.'/kit-setup/src/Dev/*.php');
    $installer = glob($monorepo.'/packages/installer/src/Dev/*.php');

    expect(array_map('basename', $kit))->toBe(array_map('basename', $installer))
        ->and($kit)->not->toBe([]);

    foreach ($kit as $file) {
        $ours = str_replace('namespace Twstec\Kit\Setup\Dev;', 'namespace Twstec\Kit\Installer\Dev;', (string) file_get_contents($file));

        expect((string) file_get_contents($monorepo.'/packages/installer/src/Dev/'.basename($file)))->toBe($ours, basename($file).' divergiu');
    }
})->skip(! is_dir($monorepo.'/packages/installer'), 'fora do monorepo');
