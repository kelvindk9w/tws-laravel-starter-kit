<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

// =============================================================================
// ARQUITETURA DOS MÓDULOS DE app/Core — a trava da divisão em pacotes.
//
// O kit vai virar pacotes instaláveis (foundation, auth, accounts, uploads;
// o admin fica fora de app/Core). Um pacote só pode depender dos que estão
// ABAIXO dele: foundation não conhece ninguém; auth conhece foundation;
// accounts conhece auth e foundation; uploads conhece os três.
//
// As camadas foundation, auth e accounts JÁ SAÍRAM de app/Core: são os
// pacotes twstec/kit-foundation (packages/foundation), twstec/kit-auth
// (packages/auth) e twstec/kit-accounts (packages/accounts), e a trava de
// cada uma — não conhecer nada do aplicativo nem das camadas de cima — mora
// na suíte do próprio pacote. Aqui ficam as regras dos módulos que ainda
// moram em app/Core, e mais uma: o código do aplicativo usa os nomes NOVOS das
// classes que saíram (Twstec\Kit\Foundation\…, Twstec\Kit\Auth\…,
// Twstec\Kit\Accounts\…, e App\Models\User para o model de usuário, que é
// do aplicativo); os nomes antigos (App\Core\<Módulo>\…) existem só como
// apelidos de compatibilidade para o que está gravado fora do código.
//
// A DEMONSTRAÇÃO do kit (App\Demo, em app/Demo e demo/) fica no topo, FORA de
// app/Core: ela pode usar qualquer peça do produto, e NENHUMA peça do produto
// pode usar a demo — nem app/Core, nem as telas (app/Filament, app/Livewire),
// nem providers, rotas, config, migrations, seeders e views. O único ponto de
// ligação permitido é o registro do provider da demo em bootstrap/providers.php
// (PRODUCT_DEMO_JUNCTIONS).
//
// Este arquivo reprova o build quando:
//
// 1. um módulo novo aparece em app/Core sem camada declarada aqui;
// 2. um módulo usa classe de uma camada ACIMA da dele (import que "sobe");
// 3. surge um CICLO entre módulos que não seja um dos grupos coesos
//    declarados (módulos que andam juntos e vão para o MESMO pacote);
// 4. o backend passa a depender das telas (Livewire/Filament do app);
// 5. qualquer arquivo do produto passa a usar App\Demo.
//
// O que ainda não pôde ser corrigido é EXCEÇÃO EXPLÍCITA, listada abaixo com
// a fase em que sai. Exceção que deixou de existir também reprova — a lista
// não pode envelhecer em silêncio.
//
// A leitura é por tokens do PHP: comentários e strings não contam, só nomes
// de classe de verdade (`use`, `new`, `::class`, tipos, `instanceof`…). Nas
// views Blade, que não são PHP puro, vale o nome escrito em qualquer lugar.
// =============================================================================

/**
 * Camadas, de baixo para cima, e os módulos de cada uma.
 *
 * As camadas foundation, auth e accounts não aparecem: são os pacotes
 * twstec/kit-foundation, twstec/kit-auth e twstec/kit-accounts, e usar classe
 * deles é sempre descer na hierarquia.
 */
const CORE_LAYERS = [
    'uploads' => ['Uploads'],
];

/**
 * Grupos coesos: ciclos aceitos porque os módulos vão juntos para o MESMO
 * pacote. Qualquer outro ciclo reprova.
 *
 * Vazia desde a extração do pacote accounts (F5): o grupo projetos ↔ chaves de
 * API foi junto para o twstec/kit-accounts, como o grupo segurança ↔ trilha ↔
 * HTTP ↔ idioma tinha ido para o twstec/kit-foundation; a suíte de cada
 * pacote confere o dele.
 *
 * @var list<list<string>>
 */
const CORE_COHESIVE_CYCLES = [];

/**
 * Módulos que saíram de app/Core para o pacote twstec/kit-foundation. O nome
 * antigo App\Core\<Módulo>\… de qualquer um deles não pode voltar ao código.
 */
const FOUNDATION_MOVED_MODULES = ['Identifiers', 'Money', 'Http', 'Security', 'Logging', 'Localization', 'Settings', 'Mail', 'Support', 'Backup', 'Audit'];

/**
 * Módulo que saiu de app/Core para o pacote twstec/kit-auth. O nome antigo
 * App\Core\Auth\… não pode voltar ao código (o model de usuário virou
 * App\Models\User, do aplicativo; o resto é Twstec\Kit\Auth\…).
 */
const AUTH_MOVED_MODULES = ['Auth'];

/**
 * Módulos que saíram de app/Core para o pacote twstec/kit-accounts. O nome
 * antigo App\Core\Tenancy\… ou App\Core\ApiKeys\… não pode voltar ao
 * código (o novo é Twstec\Kit\Accounts\Tenancy\… e
 * Twstec\Kit\Accounts\ApiKeys\…).
 */
const ACCOUNTS_MOVED_MODULES = ['Tenancy', 'ApiKeys'];

/**
 * Imports que sobem na hierarquia e ainda não puderam sair: arquivo => classes.
 *
 * Vazia desde a extração do pacote auth (F4): o model de usuário saiu de
 * app/Core e virou App\Models\User, do aplicativo, que compõe as traits dos
 * pacotes instalados (a de autenticação e a de avatar de uploads) — o pacote
 * auth não conhece uploads.
 *
 * @var array<string, list<string>>
 */
const CORE_UPWARD_EXCEPTIONS = [];

/**
 * Prefixos de código de TELA que o backend não pode usar.
 */
const CORE_FORBIDDEN_UI_PREFIXES = ['App\Livewire\\', 'App\Filament\\'];

/**
 * Diretórios do PRODUTO — tudo o que não é a demonstração — e os arquivos
 * dentro deles que podem nomear a demo (o ponto de ligação).
 */
const PRODUCT_DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views'];

/**
 * PONTO DE LIGAÇÃO da demo: o único lugar do produto que pode nomeá-la.
 *
 * Não é dívida (como CORE_UPWARD_EXCEPTIONS): é a tomada, por desenho. Por
 * isso é PERMITIDO, não obrigatório — tirar o registro desliga a demo, e o
 * produto sem ele continua passando aqui.
 */
const PRODUCT_DEMO_JUNCTIONS = [
    'bootstrap/providers.php' => ['App\Demo\Providers\DemoServiceProvider'],
];

/**
 * Nomes de classe do próprio app (App\…) em código PHP, lidos dos tokens.
 *
 * @return list<string>
 */
function appReferencesIn(string $contents): array
{
    $names = [];

    foreach (PhpToken::tokenize($contents) as $token) {
        if (! $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            continue;
        }

        $name = ltrim($token->text, '\\');

        if (str_starts_with($name, 'App\\')) {
            $names[$name] = true;
        }
    }

    return array_keys($names);
}

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
        $references[str_replace(base_path().'/', '', $file->getRealPath())] = appReferencesIn($file->getContents());
    }

    ksort($references);

    return $references;
}

/**
 * Referências à demonstração (App\Demo\…) em cada arquivo do produto.
 *
 * PHP é lido por tokens. Blade não é PHP puro (o `@php(...)` e o `{{ }}` não
 * tokenizam como código): ali vale o nome escrito em qualquer lugar, inclusive
 * em comentário — a view do produto não deve nem citar a demo.
 *
 * @return array<string, list<string>> caminho relativo => nomes
 */
function productDemoReferences(): array
{
    $references = [];

    foreach (PRODUCT_DIRECTORIES as $directory) {
        $finder = (new Finder)->files()->in(base_path($directory))->name('*.php');

        if ($directory === 'app') {
            $finder->exclude('Demo');
        }

        foreach ($finder as $file) {
            $path = str_replace(base_path().'/', '', $file->getRealPath());

            if (str_ends_with($path, '.blade.php')) {
                preg_match_all('/App\\\\+Demo(?:\\\\+[A-Za-z0-9_]+)*/', $file->getContents(), $matches);
                $names = array_values(array_unique(array_map(
                    fn (string $name): string => (string) preg_replace('/\\\\+/', '\\', $name),
                    $matches[0],
                )));
            } else {
                $names = array_values(array_filter(
                    appReferencesIn($file->getContents()),
                    fn (string $name): bool => str_starts_with($name, 'App\\Demo\\'),
                ));
            }

            if ($names !== []) {
                $references[$path] = $names;
            }
        }
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

it('mantém o produto sem dependência da demonstração (App\\Demo)', function (): void {
    $violations = [];

    foreach (productDemoReferences() as $path => $names) {
        foreach ($names as $name) {
            if (! in_array($name, PRODUCT_DEMO_JUNCTIONS[$path] ?? [], true)) {
                $violations[] = "{$path} usa {$name}";
            }
        }
    }

    expect($violations)->toBe([]);
});

it('mantém a demonstração fora de app/Core', function (): void {
    // A demo mora em App\Demo; módulo de app/Core com classe da demo seria a
    // fronteira voltando por dentro do produto.
    $demoInCore = [];

    foreach ((new Finder)->files()->in(base_path('app/Core'))->name('*.php') as $file) {
        if (preg_match('/^namespace\s+App\\\\Demo\b/m', $file->getContents()) === 1) {
            $demoInCore[] = str_replace(base_path().'/', '', $file->getRealPath());
        }
    }

    // A pasta app/Demo pode nem existir: o produto não exige a demo.
    expect($demoInCore)->toBe([]);
});

it('usa os nomes novos das classes da base, nunca os apelidos App\\Core\\<Módulo da base>', function (): void {
    // Os apelidos de compatibilidade (packages/foundation/src/Compat) existem
    // para o que está gravado FORA do código — payload de fila antigo, config
    // publicada por quem ainda não atualizou. Código novo que usa o nome
    // antigo prenderia o kit a eles e impediria de removê-los na 3.0.
    $pattern = '/^App\\\\Core\\\\('.implode('|', FOUNDATION_MOVED_MODULES).')\\\\/';
    $violations = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'demo', 'tests'] as $directory) {
        if (! is_dir(base_path($directory))) {
            continue;
        }

        foreach ((new Finder)->files()->in(base_path($directory))->name('*.php') as $file) {
            $path = str_replace(base_path().'/', '', $file->getRealPath());

            foreach (appReferencesIn($file->getContents()) as $name) {
                if (preg_match($pattern, $name) === 1) {
                    $violations[] = "{$path} usa {$name}";
                }
            }
        }
    }

    // E nenhum módulo da base voltou a morar em app/Core.
    foreach (FOUNDATION_MOVED_MODULES as $module) {
        if (is_dir(base_path("app/Core/{$module}"))) {
            $violations[] = "app/Core/{$module} existe de novo (o módulo é do pacote twstec/kit-foundation)";
        }
    }

    expect($violations)->toBe([]);
});

it('usa os nomes novos das classes de autenticação, nunca os apelidos App\\Core\\Auth', function (): void {
    // Mesma regra, para o que saiu com o pacote twstec/kit-auth: os apelidos
    // (packages/auth/src/Compat e, para o model de usuário e o comando
    // user:make-admin, que ficaram no aplicativo, app/Support/legacy-aliases.php)
    // existem só para o que está gravado fora do código. A leitura é por
    // tokens: o nome antigo escrito como TEXTO no arquivo de apelidos não conta.
    $pattern = '/^App\\\\Core\\\\('.implode('|', AUTH_MOVED_MODULES).')\\\\/';
    $violations = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'demo', 'tests'] as $directory) {
        if (! is_dir(base_path($directory))) {
            continue;
        }

        foreach ((new Finder)->files()->in(base_path($directory))->name('*.php') as $file) {
            $path = str_replace(base_path().'/', '', $file->getRealPath());

            foreach (appReferencesIn($file->getContents()) as $name) {
                if (preg_match($pattern, $name) === 1) {
                    $violations[] = "{$path} usa {$name}";
                }
            }
        }
    }

    // Nas views Blade, que não são PHP puro, vale o nome escrito.
    foreach ((new Finder)->files()->in(base_path('resources/views'))->name('*.blade.php') as $file) {
        if (preg_match('/App\\\\+Core\\\\+Auth\\\\+/', $file->getContents()) === 1) {
            $violations[] = str_replace(base_path().'/', '', $file->getRealPath()).' usa App\\Core\\Auth';
        }
    }

    // E o módulo não voltou a morar em app/Core.
    foreach (AUTH_MOVED_MODULES as $module) {
        if (is_dir(base_path("app/Core/{$module}"))) {
            $violations[] = "app/Core/{$module} existe de novo (o módulo é do pacote twstec/kit-auth)";
        }
    }

    expect($violations)->toBe([]);
});

it('usa os nomes novos das classes de contas e API, nunca os apelidos App\\Core\\Tenancy e App\\Core\\ApiKeys', function (): void {
    // Mesma regra, para o que saiu com o pacote twstec/kit-accounts: os
    // apelidos (packages/accounts/src/Compat) existem só para o que está
    // gravado fora do código — payload de fila antigo, snapshot do Livewire,
    // rota em cache. A leitura é por tokens: o nome antigo escrito como TEXTO
    // (string, nowdoc, comentário) não conta.
    $pattern = '/^App\\\\Core\\\\('.implode('|', ACCOUNTS_MOVED_MODULES).')\\\\/';
    $violations = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'demo', 'tests'] as $directory) {
        if (! is_dir(base_path($directory))) {
            continue;
        }

        foreach ((new Finder)->files()->in(base_path($directory))->name('*.php') as $file) {
            $path = str_replace(base_path().'/', '', $file->getRealPath());

            foreach (appReferencesIn($file->getContents()) as $name) {
                if (preg_match($pattern, $name) === 1) {
                    $violations[] = "{$path} usa {$name}";
                }
            }
        }
    }

    // Nas views Blade, que não são PHP puro, vale o nome escrito.
    foreach ((new Finder)->files()->in(base_path('resources/views'))->name('*.blade.php') as $file) {
        if (preg_match('/App\\\\+Core\\\\+('.implode('|', ACCOUNTS_MOVED_MODULES).')\\\\+/', $file->getContents()) === 1) {
            $violations[] = str_replace(base_path().'/', '', $file->getRealPath()).' usa App\\Core\\Tenancy ou App\\Core\\ApiKeys';
        }
    }

    // E nenhum dos módulos voltou a morar em app/Core.
    foreach (ACCOUNTS_MOVED_MODULES as $module) {
        if (is_dir(base_path("app/Core/{$module}"))) {
            $violations[] = "app/Core/{$module} existe de novo (o módulo é do pacote twstec/kit-accounts)";
        }
    }

    expect($violations)->toBe([]);
});
