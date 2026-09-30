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

    private string $output = '';

    public function composer(array $arguments, string $cwd, array $env = [], bool $capture = false): int
    {
        $binary = (string) ($this->env['COMPOSER_BINARY'] ?? '');

        $command = match (true) {
            $binary !== '' => [PHP_BINARY, $binary, ...$arguments],
            PHP_OS_FAMILY === 'Windows' => ['cmd', '/c', 'composer', ...$arguments],
            default => ['composer', ...$arguments],
        };

        if (! $capture) {
            return $this->run($command, $cwd, $env, [0 => STDIN, 1 => STDOUT, 2 => STDERR]);
        }

        // Na tela E guardado (a mensagem do Composer sobre extensão que falta
        // é lida depois): saída e erros num tubo só, repassado linha a linha.
        $this->output = '';
        $process = proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $cwd, $this->environment($env));

        if (! is_resource($process)) {
            return 1;
        }

        while (($chunk = fgets($pipes[1])) !== false) {
            fwrite(STDOUT, $chunk);
            $this->output .= $chunk;
        }

        fclose($pipes[1]);

        return proc_close($process);
    }

    public function output(): string
    {
        return $this->output;
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
        $process = proc_open($command, $descriptors, $pipes, $cwd, $this->environment($env));

        if (! is_resource($process)) {
            return 1;
        }

        return proc_close($process);
    }

    /**
     * O ambiente deste processo com as variáveis acrescentadas.
     *
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    private function environment(array $env): array
    {
        $environment = [];

        foreach ([...getenv(), ...$env] as $name => $value) {
            $environment[(string) $name] = (string) $value;
        }

        return $environment;
    }
}
