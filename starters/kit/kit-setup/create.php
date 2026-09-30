<?php

declare(strict_types=1);

// =============================================================================
// O COMANDO ÚNICO — `composer create-project twstec/kit meu-projeto`.
//
// O post-create-project-cmd do twstec/kit chama este arquivo (`@php`, o mesmo
// PHP do Composer; nada de bash — vale no Linux, no macOS e no Windows). Ele
// pergunta a interface e os módulos (ou lê TWS_KIT_STACK / TWS_KIT_WITH /
// TWS_KIT_WITHOUT) e troca este pacote pelo starter escolhido, com só os
// pacotes marcados. Ver src/CreateProject.php.
// =============================================================================

use Twstec\Kit\Setup\Choice;
use Twstec\Kit\Setup\CreateProject;
use Twstec\Kit\Setup\Dev\DockerHost;
use Twstec\Kit\Setup\Menu;
use Twstec\Kit\Setup\Output;
use Twstec\Kit\Setup\Platform\PlatformRequirements;
use Twstec\Kit\Setup\ProcessRunner;
use Twstec\Kit\Setup\Translator;

$project = dirname(__DIR__);

require $project.'/vendor/autoload.php';

// Tudo o que roda DEPOIS do `composer update` fica na memória antes dele: o
// update troca o vendor/ e o autoload em disco pelos do starter, e este
// código (kit-setup/) sai no fim.
foreach ([
    'Twstec\\Kit\\Setup\\Contracts\\Runner',
    'Twstec\\Kit\\Setup\\Modules',
    'Twstec\\Kit\\Setup\\Choice',
    'Twstec\\Kit\\Setup\\Translator',
    'Twstec\\Kit\\Setup\\Output',
    'Twstec\\Kit\\Setup\\ProcessRunner',
    'Twstec\\Kit\\Setup\\CreateProject',
    'Twstec\\Kit\\Setup\\MenuCancelled',
    'Twstec\\Kit\\Setup\\Dev\\Host',
    'Twstec\\Kit\\Setup\\Dev\\DevEnvironment',
    'Twstec\\Kit\\Setup\\Dev\\DockerHost',
    'Twstec\\Kit\\Setup\\Platform\\PlatformRequirements',
] as $class) {
    class_exists($class) || interface_exists($class);
}

$env = getenv();
$translator = new Translator(Translator::detect($env), __DIR__.'/lang');

// A máquina (o Docker e as portas): no container do instalador
// (TWS_KIT_IN_DOCKER=1), as portas são conferidas pelo próprio Docker.
$host = DockerHost::fromEnvironment($env);

// Pergunta num terminal e sem escolha no ambiente. (Com `composer -n`, o
// Composer não repassa o terminal ao script: sem perguntas.)
$menu = stream_isatty(STDIN) && ! Choice::inEnvironment($env)
    ? static fn (array $starters, string $default): ?Choice => (new Menu($translator, $starters, $default, $host, CreateProject::folderOf($project, $env)))->ask()
    : null;

// O sistema (TWS_KIT_OS_FAMILY=Windows simula o Windows — para testar o que
// acontece lá sem uma máquina Windows) e as opções do create-project de quem
// chamou (--ignore-platform-req…), quando dá para lê-las.
$osFamily = (string) ($env['TWS_KIT_OS_FAMILY'] ?? '') !== '' ? (string) $env['TWS_KIT_OS_FAMILY'] : PHP_OS_FAMILY;

exit((new CreateProject($project, $translator, new ProcessRunner($env), Output::stdout(), $env, $host, $menu, $osFamily, PlatformRequirements::callerArguments()))->run());
