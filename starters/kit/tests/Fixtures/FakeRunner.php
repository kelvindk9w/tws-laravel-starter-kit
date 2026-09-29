<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup\Tests\Fixtures;

use Twstec\Kit\Setup\Contracts\Runner;

/**
 * O Composer (e o PHP) de mentira: anota cada chamada e faz o mínimo que a
 * de verdade faria no disco — o `create-project --no-install` escreve um
 * starter falso na pasta pedida; o `post-root-package-install` cria o .env.
 */
final class FakeRunner implements Runner
{
    /**
     * @var list<array{0: string, 1: list<string>, 2: array<string, string>, 3: string}>
     */
    public array $calls = [];

    /**
     * Passo => código de saída ('create-project', 'update',
     * 'run-script post-create-project-cmd'…).
     *
     * @var array<string, int>
     */
    public array $failing = [];

    /**
     * O nome que o "pacote baixado" declara (null = o pedido).
     */
    public ?string $downloadedName = null;

    /**
     * O código de saída do `migrate:status --pending` (0 = banco pronto).
     */
    public int $databaseStatus = 0;

    /**
     * O starter "baixado" traz o Docker de desenvolvimento (compose.yaml na
     * raiz, como o publicado)?
     */
    public bool $withCompose = false;

    public function composer(array $arguments, string $cwd, array $env = []): int
    {
        $this->calls[] = ['composer', $arguments, $env, $cwd];
        $step = $arguments[0] === 'run-script' ? 'run-script '.$arguments[1] : $arguments[0];

        if (isset($this->failing[$step])) {
            return $this->failing[$step];
        }

        if ($step === 'create-project') {
            [$package] = explode(':', $arguments[1]);
            self::writeStarter($arguments[2], $this->downloadedName ?? $package);

            if ($this->withCompose) {
                file_put_contents($arguments[2].'/compose.yaml', "services: {}\n");
            }
        }

        if ($step === 'run-script post-root-package-install' && ! is_file($cwd.'/.env')) {
            copy($cwd.'/.env.example', $cwd.'/.env');
        }

        return 0;
    }

    public function quietPhp(array $arguments, string $cwd): int
    {
        $this->calls[] = ['php', $arguments, [], $cwd];

        return $this->databaseStatus;
    }

    /**
     * As chamadas ao Composer, só os argumentos (sem o `--repository`).
     *
     * @return list<string>
     */
    public function composerSteps(): array
    {
        $steps = [];

        foreach ($this->calls as [$type, $arguments]) {
            if ($type === 'composer') {
                $steps[] = implode(' ', array_filter($arguments, static fn (string $a): bool => ! str_starts_with($a, '--repository=')));
            }
        }

        return $steps;
    }

    /**
     * Um starter como o publicado: projeto, os 5 pacotes do kit, o
     * instalador em require-dev.
     */
    public static function writeStarter(string $directory, string $name): void
    {
        mkdir($directory.'/app/Models', 0777, true);
        mkdir($directory.'/lang/en', 0777, true);

        file_put_contents($directory.'/composer.json', json_encode([
            'name' => $name,
            'type' => 'project',
            'require' => [
                'php' => '^8.4',
                'laravel/framework' => '^13.17',
                'twstec/kit-accounts' => '^2.0@beta',
                'twstec/kit-admin' => '^2.0@beta',
                'twstec/kit-auth' => '^2.0@beta',
                'twstec/kit-foundation' => '^2.0@beta',
                'twstec/kit-uploads' => '^2.0@beta',
            ],
            'require-dev' => ['twstec/kit-installer' => '^2.0@beta'],
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
            'extra' => ['laravel' => ['dont-discover' => []]],
            'config' => ['allow-plugins' => new \stdClass],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($directory.'/README.md', "# {$name}\n");
        file_put_contents($directory.'/LICENSE', "MIT (starter)\n");
        file_put_contents($directory.'/.env.example', "APP_KEY=\nDB_CONNECTION=pgsql\nDB_HOST=postgres\n");
        file_put_contents($directory.'/.gitignore', "/vendor\n");
        file_put_contents($directory.'/artisan', "<?php\n");
        file_put_contents($directory.'/app/Models/User.php', "<?php\n");
        file_put_contents($directory.'/lang/en/app.php', "<?php return [];\n");
    }
}
