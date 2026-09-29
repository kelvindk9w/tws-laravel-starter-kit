<?php

declare(strict_types=1);

// =============================================================================
// Prepara o composer.json de um pacote do kit (ou de um starter —
// starters/livewire ou starters/react, ambos `type: project`) para a versão
// PUBLICADA — o que o repositório só-leitura e o Packagist vão servir.
//
// No monorepo, os pacotes se acham por PATH REPOSITORY (`../foundation`,
// `../../packages/auth`) na versão `2.x-dev`. Fora dele esses caminhos não
// existem: a cópia publicada troca a restrição dos pacotes do kit pela da
// versão (`^2.0`; no starter, `^2.0@beta` enquanto a versão for pré-release,
// porque a estabilidade mínima dele é `stable`) e tira os path repositories.
// No starter, também tira o composer.lock (ele aponta para os caminhos do
// monorepo; o projeto criado resolve o próprio lock) e a DEMONSTRAÇÃO
// (twstec/kit-demo): ela vive só no monorepo e não é publicada — quem cria
// um projeto recebe o starter limpo (a página inicial do produto; os testes
// da demo pulam sozinhos, e o instalador não a encontra para perguntar).
//
// O COMANDO ÚNICO (twstec/kit, starters/kit — também `type: project`) não
// requer pacote do kit: ele baixa o starter escolhido no create-project, com
// a restrição gravada em `extra.twstec-kit.constraint` (no monorepo,
// `2.x-dev`), que aqui vira a da versão publicada (`^2.0@beta` durante o
// beta). E sai sem o que é da suíte dele (require-dev, autoload-dev e os
// scripts de teste): o create-project instala as dependências de
// desenvolvimento do pacote raiz, e o Pest não tem o que fazer no projeto de
// quem usa o kit.
//
// Ainda no starter, o docker-compose.prod.yml publicado sai SEM o contexto de
// build `packages` (`additional_contexts: packages: ../../packages`, que só
// existe no monorepo): no projeto criado não há pasta packages/, e o
// Dockerfile de produção — o mesmo arquivo — cai no estágio `packages` vazio
// e instala os pacotes do Composer (ver docker/php/Dockerfile do starter). O
// script falha se sobrar alguma referência ao monorepo fora de comentário.
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

// A demonstração não é publicada: fora do starter publicado, em qualquer seção.
if ($isProject) {
    foreach (['require', 'require-dev', 'suggest'] as $section) {
        unset($composer[$section]['twstec/kit-demo']);
    }
}

// O comando único: a restrição do starter que ele baixa, e nada da suíte.
$isKit = ($composer['name'] ?? null) === 'twstec/kit';

if ($isKit) {
    if (! isset($composer['extra']['twstec-kit']['starters'])) {
        fwrite(STDERR, "o composer.json do twstec/kit não tem extra.twstec-kit.starters\n");
        exit(1);
    }

    $composer['extra']['twstec-kit']['constraint'] = $constraint;
    unset($composer['require-dev'], $composer['autoload-dev'], $composer['scripts']['test'], $composer['scripts']['lint']);
}

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

// Compose de produção do starter: sem o contexto `packages` do monorepo.
$compose = rtrim($dir, '/').'/docker-compose.prod.yml';

if ($isProject && is_file($compose)) {
    $yaml = (string) file_get_contents($compose);
    $yaml = (string) preg_replace(
        '/^[ \t]*# Pacotes do kit \(path repository\)[^\n]*\n[ \t]*# só no monorepo[^\n]*\n[ \t]*additional_contexts:[ \t]*\n[ \t]*packages: \.\.\/\.\.\/packages[ \t]*\n/mu',
        '',
        $yaml,
    );

    foreach (explode("\n", $yaml) as $line) {
        if (! str_starts_with(ltrim($line), '#') && (str_contains($line, 'additional_contexts') || str_contains($line, '../../packages'))) {
            fwrite(STDERR, "o docker-compose.prod.yml publicado ainda cita o monorepo: {$line}\n");
            exit(1);
        }
    }

    file_put_contents($compose, $yaml);
}

if ($isKit && (($composer['extra']['twstec-kit']['constraint'] ?? null) !== $constraint || isset($composer['require-dev']) || str_contains((string) json_encode($composer), '2.x-dev'))) {
    fwrite(STDERR, "o composer.json publicado do twstec/kit ainda tem o que é do monorepo (2.x-dev ou require-dev)\n");
    exit(1);
}

if ($isProject && str_contains((string) json_encode($composer), 'kit-demo')) {
    fwrite(STDERR, "o composer.json publicado do starter ainda cita a demonstração\n");
    exit(1);
}

fwrite(STDOUT, sprintf("%s: pacotes do kit em %s%s\n", $composer['name'], $constraint, match (true) {
    $isKit => ' (comando único: baixa o starter escolhido com essa restrição; sem a suíte)',
    $isProject => ' (projeto; sem a demonstração; composer.lock removido; compose de produção sem o contexto do monorepo)',
    default => '',
}));
