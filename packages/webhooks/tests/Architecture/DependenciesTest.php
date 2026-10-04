<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// =============================================================================
// ARQUITETURA DO PACOTE webhooks — conhece a base (foundation), a autenticação
// (auth), as contas (accounts), o Laravel e as interfaces PSR de mensagem HTTP
// (que o cliente Http do Laravel expõe), e só.
//
// webhooks depende de twstec/kit-foundation, twstec/kit-auth e
// twstec/kit-accounts; nada do kit depende dele. Telas não há (as dos
// starters ficam nos starters): a única view é o corpo do e-mail de aviso.
// Este arquivo reprova o build quando:
//
// 1. qualquer arquivo do pacote nomeia classe do APLICATIVO (App\…), da
//    camada de cima (admin), da demonstração ou de tela (Filament, Livewire,
//    Inertia);
// 2. um `use` ou nome totalmente qualificado sai do que o pacote pode usar.
//
// A leitura do PHP é por tokens: comentários e strings não contam.
// =============================================================================

const WEBHOOKS_FORBIDDEN_PREFIXES = [
    'App\\',
    'Database\\',
    'Twstec\\Kit\\Admin\\',
    'Filament\\',
    'Livewire\\',
    'Inertia\\',
    'Twstec\\Kit\\Uploads\\',
    'Twstec\\Kit\\Demo\\',
];

const WEBHOOKS_ALLOWED_ROOTS = [
    'Twstec\\Kit\\Webhooks\\',
    'Twstec\\Kit\\Accounts\\',
    'Twstec\\Kit\\Auth\\',
    'Twstec\\Kit\\Foundation\\',
    'Illuminate\\',
    'Symfony\\Component\\HttpFoundation\\',
    'Carbon\\',
    'Psr\\Http\\Message\\',
];

const WEBHOOKS_SHIPPED_DIRECTORIES = ['src', 'config', 'database', 'lang', 'examples'];

function webhooksRoot(): string
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
function webhooksClassNamesIn(string $contents): array
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
function webhooksDependencyViolations(): array
{
    $violations = [];

    foreach (WEBHOOKS_SHIPPED_DIRECTORIES as $directory) {
        foreach ((new Finder)->files()->in(webhooksRoot().'/'.$directory)->name('*.php') as $file) {
            $path = str_replace(webhooksRoot().'/', '', $file->getRealPath());
            $names = webhooksClassNamesIn($file->getContents());

            foreach ($names['all'] as $name) {
                foreach (WEBHOOKS_FORBIDDEN_PREFIXES as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $violations[$path][] = "usa {$name}";
                    }
                }
            }

            foreach ($names['absolute'] as $name) {
                $allowed = false;

                foreach (WEBHOOKS_ALLOWED_ROOTS as $root) {
                    $allowed = $allowed || str_starts_with($name.'\\', $root);
                }

                // Funções e constantes globais importadas não têm `\`.
                if (! $allowed && str_contains($name, '\\')) {
                    $violations[$path][] = "usa {$name} (fora de foundation + auth + accounts + Laravel)";
                }
            }
        }
    }

    ksort($violations);

    return $violations;
}

it('não nomeia nada do aplicativo, da camada de cima nem de tela', function (): void {
    $violations = [];

    foreach (webhooksDependencyViolations() as $path => $problems) {
        foreach (array_unique($problems) as $problem) {
            $violations[] = "{$path} {$problem}";
        }
    }

    expect($violations)->toBe([]);
});

it('não tem telas: a única view é a do e-mail de aviso', function (): void {
    $views = array_map(
        fn ($file): string => str_replace(webhooksRoot().'/', '', $file->getRealPath()),
        iterator_to_array((new Finder)->files()->in(webhooksRoot().'/resources'), false),
    );

    expect($views)->toBe(['resources/views/mail/endpoint-disabled.blade.php']);

    $blade = iterator_to_array((new Finder)->files()->in(webhooksRoot().'/src')->name('*.blade.php'), false);

    expect($blade)->toBe([]);
});

it('declara no composer.json as dependências que usa — e só as do kit, o Laravel e a extensão cURL', function (): void {
    $composer = json_decode((string) file_get_contents(webhooksRoot().'/composer.json'), true);

    expect(array_keys($composer['require']))->toBe([
        'php',
        'ext-curl',
        'laravel/framework',
        'twstec/kit-accounts',
        'twstec/kit-auth',
        'twstec/kit-foundation',
    ]);
});

it('a leitura por tokens pega o que deve pegar (a trava não é cega)', function (): void {
    $sample = <<<'PHP'
        <?php
        namespace Twstec\Kit\Webhooks\Algo;
        use App\Models\User;
        use Twstec\Kit\Accounts\Http\ApiRoutes;
        use Livewire\Component;
        // App\Demo\Coisa em comentário não conta
        $a = new \Filament\Panel;
        $b = Delivery\Outbox::class;
        $c = 'App\\Em\\String';
        PHP;

    $names = webhooksClassNamesIn($sample);

    expect($names['absolute'])->toBe(['App\Models\User', 'Twstec\Kit\Accounts\Http\ApiRoutes', 'Livewire\Component', 'Filament\Panel'])
        ->and($names['all'])->toContain('Delivery\Outbox')
        ->and($names['all'])->not->toContain('App\Demo\Coisa');
});
