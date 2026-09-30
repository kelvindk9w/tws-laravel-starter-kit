<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Closure;
use RuntimeException;
use stdClass;
use Throwable;
use Twstec\Kit\Setup\Contracts\Runner;
use Twstec\Kit\Setup\Dev\DevEnvironment;
use Twstec\Kit\Setup\Dev\Host;
use Twstec\Kit\Setup\Platform\PlatformRequirements;

/**
 * `composer create-project twstec/kit meu-projeto` — o que acontece depois
 * que o Composer baixou este pacote na pasta do projeto (o
 * post-create-project-cmd dele):
 *
 * 1. A ESCOLHA: pelo menu (terminal) ou pelo ambiente (TWS_KIT_NAME,
 *    TWS_KIT_SLOT, TWS_KIT_EXPOSE_DB, TWS_KIT_STACK, TWS_KIT_WITH,
 *    TWS_KIT_WITHOUT; sem nada, o padrão: o nome da pasta, o primeiro número
 *    com as portas livres, Livewire com todos os módulos). Escolha inválida
 *    para aqui, antes de baixar qualquer coisa.
 * 2. O STARTER escolhido é baixado pelo MESMO Composer
 *    (`create-project --no-install --no-scripts`, com a mesma restrição de
 *    versão deste pacote e os mesmos repositórios do composer.json dele —
 *    o `--repository … --add-repository` de quem chamou) numa pasta
 *    temporária DENTRO do projeto, e conferido (nome e tipo).
 * 3. Os arquivos deste pacote dão lugar aos do starter (a pasta
 *    `kit-setup`, de onde este código roda, sai por último).
 * 4. O composer.json do starter perde os módulos NÃO marcados e ganha os
 *    repositórios deste; depois, na ordem do `create-project` do próprio
 *    starter: `post-root-package-install` (o .env), `composer update` (só os
 *    pacotes marcados) e `post-create-project-cmd` (o `tws:install`, com a
 *    escolha no ambiente — TWS_KIT_WITH/TWS_KIT_WITHOUT —, que não pergunta
 *    de novo: gera a APP_KEY e o pepper e roda as migrations, ou avisa que o
 *    banco ficou para depois).
 *    O nome e o número do projeto vão junto (TWS_KIT_NAME, TWS_KIT_SLOT,
 *    TWS_KIT_EXPOSE_DB): o instalador grava no .env o Docker de
 *    desenvolvimento do projeto (portas, nome, endereço, senhas).
 * 5. O banco: com o Docker de desenvolvimento no projeto (compose.yaml), o
 *    `docker compose up -d` sobe o banco e roda as migrations; sem ele, se o
 *    do .env não respondeu, a instrução exata.
 *
 * EXTENSÕES DO PHP: as chamadas ao Composer recebem o que a pessoa pediu para
 * ignorar (COMPOSER_IGNORE_PLATFORM_REQ(S) e as opções do create-project,
 * quando dá para lê-las) e, no Windows, `ext-pcntl` e `ext-posix` (só o
 * Horizon as usa; o PHP do Windows não as tem) — o resumo avisa. Qualquer
 * outra extensão que falte para o `composer update` com a lista e as duas
 * saídas: instalar a extensão, ou o caminho só com o Docker. Ver
 * Platform/PlatformRequirements.
 *
 * DENTRO DO CONTAINER DO INSTALADOR (`docker compose run --rm instalar`,
 * TWS_KIT_IN_DOCKER=1): o projeto é montado na própria pasta baixada do
 * twstec/kit, e as mensagens falam dela ("esta pasta") e dos comandos do
 * Docker, não de `cd` e `composer`.
 *
 * FALHA LIMPA: antes do passo 3, nada do projeto mudou — a pasta temporária
 * sai e a mensagem diz para apagar a pasta e rodar de novo. Depois dele, a
 * mensagem diz o passo que falhou e os comandos exatos para terminar (ou
 * recomeçar). O código de saída é diferente de 0, e o Composer repassa.
 */
final class CreateProject
{
    /**
     * O diretório do código deste pacote dentro do projeto (sai no fim).
     */
    public const SETUP_DIRECTORY = 'kit-setup';

    /**
     * Prefixo da pasta temporária do starter, dentro do projeto.
     */
    public const TEMPORARY_PREFIX = '.tws-kit-starter-';

    /**
     * Rodando no container do instalador (a pasta é a do ZIP)?
     */
    private readonly bool $inDocker;

    /**
     * As variáveis de plataforma para TODAS as chamadas ao Composer.
     *
     * @var array<string, string>
     */
    private readonly array $platform;

    private readonly bool $windows;

    /**
     * @param  array<string, string|false>  $env
     * @param  (Closure(array<string, string>, string): ?Choice)|null  $menu  null = sem perguntas
     */
    public function __construct(
        private readonly string $directory,
        private readonly Translator $t,
        private readonly Runner $runner,
        private readonly Output $out,
        private readonly array $env,
        private readonly Host $host,
        private readonly ?Closure $menu = null,
        string $osFamily = PHP_OS_FAMILY,
        array $callerArguments = [],
    ) {
        $this->inDocker = ($env['TWS_KIT_IN_DOCKER'] ?? '') === '1';
        $this->windows = PlatformRequirements::isWindows($osFamily);
        $this->platform = PlatformRequirements::composerEnvironment($env, $osFamily, $callerArguments);
    }

    /**
     * A pasta do projeto, como a pessoa a vê (a base do nome sugerido): no
     * container, a que o Compose informa (TWS_KIT_FOLDER); fora, a própria.
     */
    public function folder(): string
    {
        return self::folderOf($this->directory, $this->env);
    }

    /**
     * @param  array<string, string|false>  $env
     */
    public static function folderOf(string $directory, array $env): string
    {
        $folder = trim((string) ($env['TWS_KIT_FOLDER'] ?? ''));

        return $folder !== '' ? $folder : basename($directory);
    }

    public function run(): int
    {
        $kit = $this->readJson($this->directory.'/composer.json');
        $config = $kit->extra->{'twstec-kit'} ?? null;

        if (! $config instanceof stdClass || ! is_string($config->constraint ?? null) || ! ($config->starters ?? null) instanceof stdClass) {
            $this->out->error($this->t->get('errors.kit_config'));

            return 1;
        }

        /** @var array<string, string> $starters */
        $starters = array_map('strval', (array) $config->starters);
        $defaultStack = (string) ($config->{'default-starter'} ?? array_key_first($starters));
        $constraint = $config->constraint;

        $this->out->line($this->text('intro', ['dir' => $this->directory]));

        // A identidade do projeto (TWS_KIT_VENDOR/TWS_KIT_LICENSE) vale nos
        // dois jeitos (menu e ambiente): conferida antes de qualquer coisa.
        $identity = Choice::identityError($this->env, $this->t);

        if ($identity !== null) {
            $this->out->error($identity);
            $this->out->line($this->text('errors.nothing_installed', ['dir' => $this->directory]));

            return 1;
        }

        $choice = $this->choose($starters, $defaultStack);

        if ($choice === null) {
            return 1;
        }

        $package = $starters[$choice->stack];
        $temporary = $this->directory.'/'.self::TEMPORARY_PREFIX.bin2hex(random_bytes(4));

        try {
            $starter = $this->download($package, $constraint, $temporary, $this->repositories($kit));

            if ($starter === null) {
                return 1;
            }

            try {
                $this->replaceWithStarter($temporary);
            } catch (Throwable $e) {
                $this->out->error($this->text('failures.replace', ['dir' => $this->directory, 'reason' => $e->getMessage()]));

                return 1;
            }
        } finally {
            $this->remove($temporary);
        }

        try {
            $this->writeComposerJson($starter, $choice, $this->repositories($kit));

            return $this->install($choice) ? $this->finish($choice, $package) : 1;
        } finally {
            $this->remove($this->directory.'/'.self::SETUP_DIRECTORY);

            // No Windows, um arquivo preso (antivírus, editor) impede a
            // remoção: o projeto funciona, mas a pasta não é dele.
            if (file_exists($this->directory.'/'.self::SETUP_DIRECTORY)) {
                $this->out->warn($this->t->get('failures.leftover', ['path' => $this->directory.'/'.self::SETUP_DIRECTORY]));
            }
        }
    }

    /**
     * @param  array<string, string>  $starters
     */
    private function choose(array $starters, string $defaultStack): ?Choice
    {
        if ($this->menu !== null) {
            $choice = ($this->menu)($starters, $defaultStack);

            if ($choice === null) {
                $this->out->warn($this->text('aborted', ['dir' => $this->directory]));
            }

            return $choice;
        }

        [$choice, $error] = Choice::fromEnvironment($this->env, array_keys($starters), $defaultStack, $this->t, $this->host, $this->folder());

        if ($choice === null) {
            $this->out->error((string) $error);
            $this->out->line($this->text('errors.nothing_installed', ['dir' => $this->directory]));

            return null;
        }

        $this->out->line($this->t->get('plan.from_environment', [
            'name' => (string) $choice->name,
            'slot' => (string) $choice->slot,
            'stack' => $this->t->get("stack.{$choice->stack}"),
            'modules' => $choice->modules === [] ? $this->t->get('modules.none') : $this->t->modules($choice->modules),
        ]));

        return $choice;
    }

    /**
     * O starter, só o pacote (sem dependências nem scripts), na pasta
     * temporária — e conferido. Null (com a mensagem) quando falha.
     *
     * @param  list<stdClass>  $repositories
     */
    private function download(string $package, string $constraint, string $temporary, array $repositories): ?stdClass
    {
        $this->out->step($this->t->get('steps.download', ['package' => $package, 'constraint' => $constraint]));

        $arguments = ['create-project', "{$package}:{$constraint}", $temporary, '--no-install', '--no-scripts', '--no-interaction'];

        foreach ($this->lookupRepositories($repositories) as $repository) {
            $arguments[] = '--repository='.json_encode($repository, JSON_UNESCAPED_SLASHES);
        }

        if ($this->runner->composer($arguments, $this->directory, $this->platform) !== 0 || ! is_file($temporary.'/composer.json')) {
            $this->out->error($this->text('failures.download', ['package' => $package, 'dir' => $this->directory]));

            return null;
        }

        $starter = $this->readJson($temporary.'/composer.json');

        if (($starter->name ?? null) !== $package || ($starter->type ?? null) !== 'project') {
            $this->out->error($this->text('failures.unexpected', ['package' => $package, 'dir' => $this->directory]));

            return null;
        }

        return $starter;
    }

    /**
     * Os arquivos deste pacote saem (menos o vendor/, que o `composer update`
     * reaproveita, e a pasta do código em execução, que sai no fim); os do
     * starter entram. Mesma pasta de origem e destino: renomear, não copiar.
     */
    private function replaceWithStarter(string $temporary): void
    {
        $this->out->step($this->t->get('steps.replace'));

        $keep = ['vendor', self::SETUP_DIRECTORY, basename($temporary)];

        foreach ($this->entries($this->directory) as $entry) {
            if (! in_array($entry, $keep, true)) {
                $this->remove($this->directory.'/'.$entry);
            }
        }

        foreach ($this->entries($temporary) as $entry) {
            if ($entry === 'vendor' || $entry === self::SETUP_DIRECTORY) {
                continue;
            }

            if (! @rename($temporary.'/'.$entry, $this->directory.'/'.$entry)) {
                throw new RuntimeException($entry);
            }
        }
    }

    /**
     * O composer.json do projeto: o do starter sem os módulos não marcados e
     * com os repositórios deste pacote (os mesmos do create-project de quem
     * chamou) na frente dos do starter.
     *
     * @param  list<stdClass>  $repositories
     */
    private function writeComposerJson(stdClass $starter, Choice $choice, array $repositories): void
    {
        foreach ($choice->without() as $module) {
            foreach (['require', 'require-dev'] as $section) {
                if (isset($starter->{$section}) && $starter->{$section} instanceof stdClass) {
                    unset($starter->{$section}->{Modules::package($module)});
                }
            }
        }

        $merged = [];

        foreach ([...$repositories, ...array_values((array) ($starter->repositories ?? []))] as $repository) {
            $merged[json_encode($repository)] = $repository;
        }

        if ($merged !== []) {
            $starter->repositories = array_values($merged);
        }

        file_put_contents(
            $this->directory.'/composer.json',
            json_encode($starter, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $this->out->line($this->t->get('steps.modules', [
            'modules' => $this->t->modules([...Modules::REQUIRED, ...$choice->modules]),
        ]));
    }

    /**
     * Os passos do create-project do próprio starter, na ordem dele.
     */
    private function install(Choice $choice): bool
    {
        $steps = [
            ['steps.env', ['run-script', 'post-root-package-install', '--no-interaction'], []],
            ['steps.install', ['update', '--no-interaction'], []],
            ['steps.installer', ['run-script', 'post-create-project-cmd', '--no-interaction'], [
                'TWS_KIT_WITH' => implode(',', $choice->modules),
                'TWS_KIT_WITHOUT' => implode(',', $choice->without()),
                // O instalador do projeto fala a língua do menu.
                'APP_LOCALE' => $this->t->locale,
                // O resumo final é o deste comando (o aviso do Windows, uma vez).
                'TWS_KIT_FROM_KIT' => '1',
                // O Docker de desenvolvimento do projeto: o instalador grava
                // no .env (e não pergunta de novo).
                ...$this->devEnvironment($choice),
            ]],
        ];

        foreach ($steps as $index => [$label, $arguments, $env]) {
            $this->out->step($this->t->get($label));

            // O `composer update` guarda a saída: a mensagem de extensão que
            // falta é lida dela.
            $update = $arguments[0] === 'update';

            if ($this->runner->composer($arguments, $this->directory, [...$this->platform, ...$env], $update) === 0) {
                continue;
            }

            $this->out->line();
            $this->out->error($this->text('failures.incomplete', ['dir' => $this->directory, 'step' => $this->t->get($label)]));

            $missing = $update ? PlatformRequirements::missingExtensions($this->runner->output()) : [];

            if ($missing !== []) {
                $this->missingExtensions($missing);
            }

            // No container, a pasta já é um projeto pela metade, sem o
            // instalador: recomeçar do ZIP é o caminho simples.
            if ($this->inDocker) {
                $this->out->line($this->text('failures.restart', ['dir' => $this->directory]));

                return false;
            }

            $this->out->line($this->t->get($missing === [] ? 'failures.finish' : 'failures.finish_after_extensions'));
            $this->out->command('cd '.$this->directory);

            // O que foi ignorado aqui vale para os comandos de terminar.
            foreach ($this->platform as $name => $value) {
                $this->out->command($this->windows ? "\$env:{$name} = \"{$value}\"" : "export {$name}={$value}");
            }

            foreach (array_slice($steps, $index) as [, $remaining]) {
                $this->out->command('composer '.implode(' ', array_slice($remaining, 0, -1)));
            }

            $this->out->line($this->t->get('failures.restart', ['dir' => $this->directory]));

            return false;
        }

        return true;
    }

    /**
     * Extensões do PHP que faltam (e não são dispensáveis): a lista e as duas
     * saídas — instalar, ou o caminho só com o Docker.
     *
     * @param  list<string>  $missing
     */
    private function missingExtensions(array $missing): void
    {
        $this->out->line();
        $this->out->error($this->t->get('extensions.missing', ['extensions' => implode(', ', $missing)]));
        $this->out->line($this->t->get('extensions.install'));
        $this->out->line($this->t->get('extensions.windows', [
            'lines' => implode(', ', array_map(static fn (string $e): string => "extension={$e}", $missing)),
        ]));
        $this->out->line($this->t->get('extensions.linux', [
            'packages' => implode(' ', array_map(static fn (string $e): string => "php8.4-{$e}", $missing)),
        ]));
        $this->out->line($this->t->get('extensions.mac'));
        $this->out->line($this->t->get('extensions.docker'));
        $this->out->line();
    }

    private function finish(Choice $choice, string $package): int
    {
        $this->out->line();

        $docker = is_file($this->directory.'/compose.yaml') && $choice->name !== null && $choice->slot !== null;

        if ($docker) {
            $this->out->info($this->t->get('database.docker'));
        } elseif ($this->runner->quietPhp(['artisan', 'migrate:status', '--pending=1', '--no-interaction'], $this->directory) === 0) {
            $this->out->info($this->t->get('database.ready'));
        } else {
            $this->out->warn($this->t->get('database.unreachable', [
                'connection' => $this->envValue('DB_CONNECTION') ?? '?',
                'host' => $this->envValue('DB_HOST') ?? '-',
            ]));
            $this->out->line($this->t->get('database.how'));
            $this->out->command('php artisan migrate');
        }

        $this->out->line();
        $this->out->info($this->text('done.heading', ['dir' => $this->directory]));
        $this->out->line($this->t->get('done.stack', ['stack' => $this->t->get("stack.{$choice->stack}"), 'package' => $package]));
        $this->out->line($this->t->get('done.modules', [
            'modules' => $this->t->modules([...Modules::REQUIRED, ...$choice->modules]),
        ]));

        if ($docker) {
            $this->dockerSummary($choice);
        } else {
            $this->out->line($this->t->get('done.next'));
            $this->out->command('cd '.$this->directory);
            $this->out->command('npm install && npm run build');
            $this->out->command('composer dev');
        }

        // Windows: o Horizon não roda nativo (sem pcntl/posix, ignoradas na
        // instalação) — o resto do aplicativo sim.
        if ($this->windows && ! $this->inDocker) {
            $this->out->line();
            $this->out->warn($this->t->get('extensions.windows_horizon'));
        }

        $this->out->line();

        return 0;
    }

    /**
     * O Docker de desenvolvimento do projeto: o nome, os endereços e como
     * subir.
     */
    private function dockerSummary(Choice $choice): void
    {
        $name = (string) $choice->name;
        $ports = DevEnvironment::ports((int) $choice->slot);

        $this->out->line($this->t->get('done.docker', [
            'name' => $name,
            'slot' => (string) $choice->slot,
        ]));
        $this->out->line($this->t->get('done.site', ['url' => DevEnvironment::url($name, $ports['site'])]));
        $this->out->line($this->t->get('done.mail', ['url' => DevEnvironment::url($name, $ports['mail'])]));
        $this->out->line($this->t->get($choice->exposeDatabase ? 'done.database_exposed' : 'done.database_internal', [
            'port' => (string) $ports['database'],
        ]));
        $this->out->line();
        $this->out->line($this->t->get('done.next'));

        if (! $this->inDocker) {
            $this->out->command('cd '.$this->directory);
        }

        $this->out->command('docker compose up -d');
        $this->out->line($this->t->get('done.open', ['url' => DevEnvironment::url($name, $ports['site'])]));
    }

    /**
     * As variáveis do Docker de desenvolvimento para o instalador do starter.
     *
     * @return array<string, string>
     */
    private function devEnvironment(Choice $choice): array
    {
        if ($choice->name === null || $choice->slot === null) {
            return [];
        }

        return [
            'TWS_KIT_NAME' => $choice->name,
            'TWS_KIT_SLOT' => (string) $choice->slot,
            'TWS_KIT_EXPOSE_DB' => $choice->exposeDatabase ? '1' : '0',
        ];
    }

    /**
     * O texto — no container do instalador, a versão dele quando existe
     * (docker.<chave>: "esta pasta" e os comandos do Docker no lugar de
     * `cd` e `composer`).
     *
     * @param  array<string, string>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        if ($this->inDocker && $this->t->has("docker.{$key}")) {
            return $this->t->get("docker.{$key}", $replace);
        }

        return $this->t->get($key, $replace);
    }

    /**
     * Os repositórios do composer.json deste pacote — os que quem chamou
     * acrescentou com `--repository … --add-repository` (no uso normal, não
     * há nenhum: o Packagist). Os `path` do monorepo não existem aqui.
     *
     * @return list<stdClass>
     */
    private function repositories(stdClass $kit): array
    {
        $repositories = [];

        foreach ((array) ($kit->repositories ?? []) as $repository) {
            if ($repository instanceof stdClass) {
                $repositories[] = $repository;
            }
        }

        return $repositories;
    }

    /**
     * Os repositórios para ACHAR o starter: os mesmos, com os caminhos
     * relativos (artifact, path) resolvidos a partir do projeto — a pasta
     * temporária fica noutro nível. Com repositórios próprios, o
     * create-project não consulta o Packagist sozinho: ele entra no fim,
     * salvo se o composer.json o desligou.
     *
     * @param  list<stdClass>  $repositories
     * @return list<array<string, mixed>|stdClass>
     */
    private function lookupRepositories(array $repositories): array
    {
        if ($repositories === []) {
            return [];
        }

        $lookup = [];
        $packagist = true;

        foreach ($repositories as $repository) {
            $repository = clone $repository;

            if (($repository->{'packagist.org'} ?? null) === false || ($repository->packagist ?? null) === false) {
                $packagist = false;

                continue;
            }

            $url = (string) ($repository->url ?? '');

            if (in_array($repository->type ?? null, ['artifact', 'path'], true) && $url !== '' && ! $this->isAbsolute($url)) {
                $repository->url = $this->directory.'/'.$url;
            }

            $lookup[] = $repository;
        }

        if ($packagist) {
            $lookup[] = ['type' => 'composer', 'url' => 'https://repo.packagist.org'];
        }

        return $lookup;
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[A-Za-z]:[\\\\/]|^[a-z][a-z0-9+.-]*://~i', $path) === 1;
    }

    private function envValue(string $key): ?string
    {
        $env = @file_get_contents($this->directory.'/.env');

        if (! is_string($env) || preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*"?([^"\r\n]*)"?\s*$/m', $env, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * @return list<string>
     */
    private function entries(string $directory): array
    {
        return array_values(array_diff((array) scandir($directory), ['.', '..']));
    }

    private function readJson(string $path): stdClass
    {
        $json = json_decode((string) @file_get_contents($path));

        return $json instanceof stdClass ? $json : new stdClass;
    }

    /**
     * Apaga arquivo ou pasta (sem seguir link).
     */
    private function remove(string $path): void
    {
        if (is_link($path)) {
            PHP_OS_FAMILY === 'Windows' && is_dir($path) ? @rmdir($path) : @unlink($path);

            return;
        }

        if (is_file($path)) {
            // Pasta sem escrita: nem tenta (o aviso do fim cobre).
            if (is_writable(dirname($path))) {
                @unlink($path);
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach ($this->entries($path) as $entry) {
            $this->remove($path.'/'.$entry);
        }

        if ($this->entries($path) === [] && is_writable(dirname($path))) {
            @rmdir($path);
        }
    }
}
