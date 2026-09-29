<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

use Twstec\Kit\Setup\Contracts\Runner;

/**
 * Os processos de verdade, por proc_open com a lista de argumentos (sem
 * shell: nada de bash, nada de aspas por sistema operacional).
 *
 * O Composer é o mesmo que roda o create-project: ele se anuncia em
 * COMPOSER_BINARY para os scripts, e o PHP é o do `@php` (PHP_BINARY). Sem
 * COMPOSER_BINARY (o script chamado à mão), usa o `composer` do PATH — no
 * Windows, pelo `cmd /c`, que acha o composer.bat.
 */
final class ProcessRunner implements Runner
{
    /**
     * @param  array<string, string|false>  $env
     */
    public function __construct(private readonly array $env) {}

    public function composer(array $arguments, string $cwd, array $env = []): int
    {
        $binary = (string) ($this->env['COMPOSER_BINARY'] ?? '');

        $command = match (true) {
            $binary !== '' => [PHP_BINARY, $binary, ...$arguments],
            PHP_OS_FAMILY === 'Windows' => ['cmd', '/c', 'composer', ...$arguments],
            default => ['composer', ...$arguments],
        };

        return $this->run($command, $cwd, $env, [0 => STDIN, 1 => STDOUT, 2 => STDERR]);
    }

    public function quietPhp(array $arguments, string $cwd): int
    {
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

        return $this->run([PHP_BINARY, ...$arguments], $cwd, [], [
            0 => ['file', $null, 'r'],
            1 => ['file', $null, 'w'],
            2 => ['file', $null, 'w'],
        ]);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @param  array<int, mixed>  $descriptors
     */
    private function run(array $command, string $cwd, array $env, array $descriptors): int
    {
        $environment = [];

        foreach ([...getenv(), ...$env] as $name => $value) {
            $environment[(string) $name] = (string) $value;
        }

        $process = proc_open($command, $descriptors, $pipes, $cwd, $environment);

        if (! is_resource($process)) {
            return 1;
        }

        return proc_close($process);
    }
}
