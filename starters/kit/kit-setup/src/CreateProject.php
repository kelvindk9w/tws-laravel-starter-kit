<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Closure;
use RuntimeException;
use stdClass;
use Throwable;
use Twstec\Kit\Setup\Contracts\Runner;

/**
 * `composer create-project twstec/kit meu-projeto` — o que acontece depois
 * que o Composer baixou este pacote na pasta do projeto (o
 * post-create-project-cmd dele):
 *
 * 1. A ESCOLHA: pelo menu (terminal) ou pelo ambiente (TWS_KIT_STACK,
 *    TWS_KIT_WITH, TWS_KIT_WITHOUT; sem nada, o padrão: Livewire com todos os
 *    módulos). Escolha inválida para aqui, antes de baixar qualquer coisa.
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
 * 5. O banco: se o do .env não respondeu, a instrução exata.
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
     * @param  array<string, string|false>  $env
     * @param  (Closure(array<string, string>, string): ?Choice)|null  $menu  null = sem perguntas
     */
    public function __construct(
        private readonly string $directory,
        private readonly Translator $t,
        private readonly Runner $runner,
        private readonly Output $out,
        private readonly array $env,
        private readonly ?Closure $menu = null,
    ) {}

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

        $this->out->line($this->t->get('intro', ['dir' => $this->directory]));

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
                $this->out->error($this->t->get('failures.replace', ['dir' => $this->directory, 'reason' => $e->getMessage()]));

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
                $this->out->warn($this->t->get('aborted', ['dir' => $this->directory]));
            }

            return $choice;
        }

        [$choice, $error] = Choice::fromEnvironment($this->env, array_keys($starters), $defaultStack, $this->t);

        if ($choice === null) {
            $this->out->error((string) $error);
            $this->out->line($this->t->get('errors.nothing_installed', ['dir' => $this->directory]));

            return null;
        }

        $this->out->line($this->t->get('plan.from_environment', [
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

        if ($this->runner->composer($arguments, $this->directory) !== 0 || ! is_file($temporary.'/composer.json')) {
            $this->out->error($this->t->get('failures.download', ['package' => $package, 'dir' => $this->directory]));

            return null;
        }

        $starter = $this->readJson($temporary.'/composer.json');

        if (($starter->name ?? null) !== $package || ($starter->type ?? null) !== 'project') {
            $this->out->error($this->t->get('failures.unexpected', ['package' => $package, 'dir' => $this->directory]));

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
            ]],
        ];

        foreach ($steps as $index => [$label, $arguments, $env]) {
            $this->out->step($this->t->get($label));

            if ($this->runner->composer($arguments, $this->directory, $env) === 0) {
                continue;
            }

            $this->out->line();
            $this->out->error($this->t->get('failures.incomplete', ['dir' => $this->directory, 'step' => $this->t->get($label)]));
            $this->out->line($this->t->get('failures.finish'));
            $this->out->command('cd '.$this->directory);

            foreach (array_slice($steps, $index) as [, $remaining]) {
                $this->out->command('composer '.implode(' ', array_slice($remaining, 0, -1)));
            }

            $this->out->line($this->t->get('failures.restart', ['dir' => $this->directory]));

            return false;
        }

        return true;
    }

    private function finish(Choice $choice, string $package): int
    {
        $this->out->line();

        if ($this->runner->quietPhp(['artisan', 'migrate:status', '--pending=1', '--no-interaction'], $this->directory) === 0) {
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
        $this->out->info($this->t->get('done.heading', ['dir' => $this->directory]));
        $this->out->line($this->t->get('done.stack', ['stack' => $this->t->get("stack.{$choice->stack}"), 'package' => $package]));
        $this->out->line($this->t->get('done.modules', [
            'modules' => $this->t->modules([...Modules::REQUIRED, ...$choice->modules]),
        ]));
        $this->out->line($this->t->get('done.next'));
        $this->out->command('cd '.$this->directory);
        $this->out->command('npm install && npm run build');
        $this->out->command('composer dev');
        $this->out->line();

        return 0;
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
