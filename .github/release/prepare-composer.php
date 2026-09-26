<?php

declare(strict_types=1);

// =============================================================================
// Prepara o composer.json de um pacote do kit (ou do starter) para a versão
// PUBLICADA — o que o repositório só-leitura e o Packagist vão servir.
//
// No monorepo, os pacotes se acham por PATH REPOSITORY (`../foundation`,
// `../../packages/auth`) na versão `2.x-dev`. Fora dele esses caminhos não
// existem: a cópia publicada troca a restrição dos pacotes do kit pela da
// versão (`^2.0`; no starter, `^2.0@beta` enquanto a versão for pré-release,
// porque a estabilidade mínima dele é `stable`) e tira os path repositories.
// No starter, também tira o composer.lock (ele aponta para os caminhos do
// monorepo; o projeto criado resolve o próprio lock).
//
// Uso:
//   php .github/release/prepare-composer.php <pasta> <versão> [opções]
//     --set-version          grava "version" no composer.json (só para o
//                            repositório `artifact` da simulação — no
//                            Packagist quem dá a versão é a tag)
//     --artifacts=<pasta>    (starter) acrescenta o repositório `artifact` com
//                            os zips dos pacotes (simulação)
//
// Usado pelo workflow de split (DESLIGADO — .github/workflows/split.yml) e pela
// simulação da instalação publicada (.github/release/simulate-install.sh, que
// roda no CI).
// =============================================================================

$args = array_slice($argv, 1);
$options = [];
$positional = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
        $options[$name] = $value;
    } else {
        $positional[] = $arg;
    }
}

[$dir, $version] = array_pad($positional, 2, null);

if ($dir === null || $version === null || preg_match('/^v?(\d+)\.(\d+)\.\d+(?:-([A-Za-z]+)[.\d]*)?$/', $version, $m) !== 1) {
    fwrite(STDERR, "uso: prepare-composer.php <pasta> <versão X.Y.Z[-beta.N]> [--set-version] [--artifacts=<pasta>]\n");
    exit(2);
}

$version = ltrim($version, 'v');
$major = $m[1];
$stability = strtolower($m[3] ?? '');
$path = rtrim($dir, '/').'/composer.json';
$composer = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$isProject = ($composer['type'] ?? 'library') === 'project';

// Restrição dos pacotes do kit na versão publicada. A flag de estabilidade só
// vale no pacote raiz (o projeto): numa biblioteca o Composer a ignora.
$constraint = "^{$major}.0".($isProject && $stability !== '' ? '@'.match ($stability) {
    'alpha', 'a' => 'alpha',
    'beta', 'b' => 'beta',
    'rc' => 'RC',
    default => 'dev',
} : '');

foreach (['require', 'require-dev'] as $section) {
    foreach ($composer[$section] ?? [] as $package => $current) {
        if (str_starts_with($package, 'twstec/kit-')) {
            $composer[$section][$package] = $constraint;
        }
    }
}

// Path repositories do monorepo saem; outros (se um dia houver) ficam.
$repositories = array_values(array_filter(
    $composer['repositories'] ?? [],
    static fn (array $repository): bool => ($repository['type'] ?? null) !== 'path',
));

if (isset($options['artifacts'])) {
    array_unshift($repositories, ['type' => 'artifact', 'url' => (string) $options['artifacts']]);
}

if ($repositories === []) {
    unset($composer['repositories']);
} else {
    $composer['repositories'] = $repositories;
}

if (isset($options['set-version'])) {
    $composer = ['name' => $composer['name'], 'version' => $version, ...array_diff_key($composer, ['name' => true])];
}

file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

if ($isProject && is_file(rtrim($dir, '/').'/composer.lock')) {
    unlink(rtrim($dir, '/').'/composer.lock');
}

fwrite(STDOUT, sprintf("%s: pacotes do kit em %s%s\n", $composer['name'], $constraint, $isProject ? ' (projeto; composer.lock removido)' : ''));
