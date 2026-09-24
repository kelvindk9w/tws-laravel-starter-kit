<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// =============================================================================
// ARQUITETURA DOS MÓDULOS DE app/Core — a trava da divisão em pacotes.
//
// O kit vai virar pacotes instaláveis (foundation, auth, accounts, uploads;
// o admin fica fora de app/Core). Um pacote só pode depender dos que estão
// ABAIXO dele: foundation não conhece ninguém; auth conhece foundation;
// accounts conhece auth e foundation; uploads conhece os três. A parte de
// demonstração (demo) fica no topo e sai do produto depois.
//
// Este arquivo reprova o build quando:
//
// 1. um módulo novo aparece em app/Core sem camada declarada aqui;
// 2. um módulo usa classe de uma camada ACIMA da dele (import que "sobe");
// 3. surge um CICLO entre módulos que não seja um dos grupos coesos
//    declarados (módulos que andam juntos e vão para o MESMO pacote);
// 4. o backend passa a depender das telas (Livewire/Filament do app).
//
// O que ainda não pôde ser corrigido é EXCEÇÃO EXPLÍCITA, listada abaixo com
// a fase em que sai. Exceção que deixou de existir também reprova — a lista
// não pode envelhecer em silêncio.
//
// A leitura é por tokens do PHP: comentários e strings não contam, só nomes
// de classe de verdade (`use`, `new`, `::class`, tipos, `instanceof`…).
// =============================================================================

/**
 * Camadas, de baixo para cima, e os módulos de cada uma.
 *
 * Audit entra em foundation: a trilha de auditoria de ações é usada pelas
 * configurações editáveis (Settings, foundation) e por comandos de todos os
 * módulos — não pode depender de nenhum deles.
 */
const CORE_LAYERS = [
    'foundation' => ['Identifiers', 'Money', 'Http', 'Security', 'Logging', 'Localization', 'Settings', 'Mail', 'Support', 'Backup', 'Audit'],
    'auth' => ['Auth'],
    'accounts' => ['Tenancy', 'ApiKeys'],
    'uploads' => ['Uploads'],
    'demo' => ['Catalog', 'Showcase', 'Contact'],
];

/**
 * Grupos coesos: ciclos aceitos porque os módulos vão juntos para o MESMO
 * pacote. Qualquer outro ciclo reprova.
 *
 *   Segurança ↔ trilha de requisições ↔ HTTP (+ idioma, que usa o
 *   redirecionamento seguro do HTTP e é usado pelo limite da borda) → foundation.
 *   Projetos ↔ chaves de API → accounts.
 */
const CORE_COHESIVE_CYCLES = [
    ['Http', 'Localization', 'Logging', 'Security'],
    ['ApiKeys', 'Tenancy'],
];

/**
 * Imports que sobem na hierarquia e ainda não puderam sair: arquivo => classes.
 */
const CORE_UPWARD_EXCEPTIONS = [
    // O model do usuário aplica a trait de avatar do módulo de Uploads (a
    // relação saiu do model; só a aplicação da trait ficou). Sai na extração
    // do pacote auth (F4): o User do starter passa a compor as traits dos
    // pacotes instalados, e o User do pacote não conhece uploads.
    'app/Core/Auth/Models/User.php' => ['App\Core\Uploads\Concerns\HasAvatar'],
];

/**
 * Prefixos de código de TELA que o backend não pode usar.
 */
const CORE_FORBIDDEN_UI_PREFIXES = ['App\Livewire\\', 'App\Filament\\'];

/**
 * Nomes de classe do próprio app (App\…) referenciados por cada arquivo de
 * app/Core, lidos dos tokens do PHP.
 *
 * @return array<string, list<string>> caminho relativo => nomes totalmente qualificados
 */
function coreAppReferences(): array
{
    static $references = null;

    if ($references !== null) {
        return $references;
    }

    $references = [];

    foreach ((new Finder)->files()->in(base_path('app/Core'))->name('*.php') as $file) {
        $names = [];

        foreach (PhpToken::tokenize($file->getContents()) as $token) {
            if (! $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $name = ltrim($token->text, '\\');

            if (str_starts_with($name, 'App\\')) {
                $names[$name] = true;
            }
        }

        $references[str_replace(base_path().'/', '', $file->getRealPath())] = array_keys($names);
    }

    ksort($references);

    return $references;
}

/**
 * Módulo de app/Core de um caminho de arquivo (`app/Core/<Módulo>/…`) ou de
 * um nome de classe (`App\Core\<Módulo>\…`); null fora de app/Core.
 */
function coreModuleOf(string $pathOrClass): ?string
{
    if (preg_match('#^app/Core/([^/]+)/#', $pathOrClass, $match) === 1) {
        return $match[1];
    }

    if (preg_match('/^App\\\\Core\\\\([^\\\\]+)\\\\/', $pathOrClass, $match) === 1) {
        return $match[1];
    }

    return null;
}

/**
 * Posição da camada do módulo (0 = foundation); null se não declarado.
 */
function coreLayerRank(string $module): ?int
{
    foreach (array_keys(CORE_LAYERS) as $rank => $layer) {
        if (in_array($module, CORE_LAYERS[$layer], true)) {
            return $rank;
        }
    }

    return null;
}

/**
 * Grafo de dependências entre módulos: módulo => módulos que ele usa.
 *
 * @return array<string, list<string>>
 */
function coreModuleGraph(): array
{
    $graph = [];

    foreach (coreAppReferences() as $path => $names) {
        $from = coreModuleOf($path);

        if ($from === null) {
            continue;
        }

        $graph[$from] ??= [];

        foreach ($names as $name) {
            $to = coreModuleOf($name);

            if ($to !== null && $to !== $from && ! in_array($to, $graph[$from], true)) {
                $graph[$from][] = $to;
            }
        }
    }

    ksort($graph);

    return $graph;
}

/**
 * Componentes fortemente conexos com mais de um módulo (os ciclos), pelo
 * algoritmo de Tarjan. Cada grupo vem ordenado, e a lista também.
 *
 * @param  array<string, list<string>>  $graph
 * @return list<list<string>>
 */
function coreCycles(array $graph): array
{
    $index = 0;
    $indices = [];
    $lowlinks = [];
    $stack = [];
    $onStack = [];
    $components = [];

    $connect = function (string $node) use (&$connect, &$index, &$indices, &$lowlinks, &$stack, &$onStack, &$components, $graph): void {
        $indices[$node] = $lowlinks[$node] = $index++;
        $stack[] = $node;
        $onStack[$node] = true;

        foreach ($graph[$node] ?? [] as $next) {
            if (! isset($indices[$next])) {
                $connect($next);
                $lowlinks[$node] = min($lowlinks[$node], $lowlinks[$next]);
            } elseif ($onStack[$next] ?? false) {
                $lowlinks[$node] = min($lowlinks[$node], $indices[$next]);
            }
        }

        if ($lowlinks[$node] === $indices[$node]) {
            $component = [];

            do {
                $member = array_pop($stack);
                $onStack[$member] = false;
                $component[] = $member;
            } while ($member !== $node);

            if (count($component) > 1) {
                sort($component);
                $components[] = $component;
            }
        }
    };

    foreach (array_keys($graph) as $node) {
        if (! isset($indices[$node])) {
            $connect($node);
        }
    }

    usort($components, fn (array $a, array $b): int => strcmp(implode(',', $a), implode(',', $b)));

    return $components;
}

it('declara a camada de todo módulo de app/Core, uma vez só', function (): void {
    $declared = array_merge(...array_values(CORE_LAYERS));

    expect($declared)->toHaveCount(count(array_unique($declared)));

    $modules = [];

    foreach ((new Finder)->directories()->in(base_path('app/Core'))->depth(0) as $directory) {
        $modules[] = $directory->getFilename();
    }

    sort($modules);
    sort($declared);

    expect($modules)->toBe($declared);
});

it('não deixa módulo usar classe de uma camada acima da dele', function (): void {
    $violations = [];
    $usedExceptions = [];

    foreach (coreAppReferences() as $path => $names) {
        $from = coreModuleOf($path);
        $fromRank = $from === null ? null : coreLayerRank($from);

        if ($fromRank === null) {
            continue;
        }

        foreach ($names as $name) {
            $to = coreModuleOf($name);
            $toRank = $to === null ? null : coreLayerRank($to);

            if ($toRank === null || $toRank <= $fromRank) {
                continue;
            }

            if (in_array($name, CORE_UPWARD_EXCEPTIONS[$path] ?? [], true)) {
                $usedExceptions[$path][] = $name;

                continue;
            }

            $violations[] = sprintf('%s (%s) usa %s (%s)', $path, array_keys(CORE_LAYERS)[$fromRank], $name, array_keys(CORE_LAYERS)[$toRank]);
        }
    }

    expect($violations)->toBe([]);

    // Exceção que não é mais usada sai da lista.
    foreach (CORE_UPWARD_EXCEPTIONS as $path => $names) {
        foreach ($names as $name) {
            expect($usedExceptions[$path] ?? [])->toContain($name);
        }
    }
});

it('só aceita ciclos entre módulos do mesmo grupo coeso', function (): void {
    $expected = array_map(function (array $group): array {
        sort($group);

        return $group;
    }, CORE_COHESIVE_CYCLES);

    usort($expected, fn (array $a, array $b): int => strcmp(implode(',', $a), implode(',', $b)));

    expect(coreCycles(coreModuleGraph()))->toBe($expected);

    // E cada grupo coeso cabe numa camada só — nenhum ciclo atravessa pacotes.
    foreach (CORE_COHESIVE_CYCLES as $group) {
        expect(array_unique(array_map(coreLayerRank(...), $group)))->toHaveCount(1);
    }
});

it('mantém o backend sem dependência das telas (Livewire/Filament do app)', function (): void {
    $violations = [];

    foreach (coreAppReferences() as $path => $names) {
        foreach ($names as $name) {
            foreach (CORE_FORBIDDEN_UI_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $violations[] = "{$path} usa {$name}";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
