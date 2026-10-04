<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Tests\Support;

use RuntimeException;

/**
 * Sobe o RECEPTOR DE TESTE (receiver-router.php) num processo do servidor
 * embutido do PHP, em 127.0.0.1 e numa porta livre, e lê o que ele recebeu.
 */
final class Receiver
{
    /**
     * @var resource|null
     */
    private $process = null;

    public readonly int $port;

    public readonly string $log;

    public readonly string $secrets;

    public function __construct()
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new RuntimeException('Sem porta livre para o receptor de teste.');
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        $this->port = (int) substr($name, strrpos($name, ':') + 1);
        $this->log = tempnam(sys_get_temp_dir(), 'kit-webhooks-log-') ?: throw new RuntimeException('tempnam');
        $this->secrets = tempnam(sys_get_temp_dir(), 'kit-webhooks-secrets-') ?: throw new RuntimeException('tempnam');

        $this->process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$this->port, __DIR__.'/receiver-router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['RECEIVER_LOG' => $this->log, 'RECEIVER_SECRETS' => $this->secrets, 'PATH' => (string) getenv('PATH')],
        ) ?: null;

        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $probe = @fsockopen('127.0.0.1', $this->port, $errno, $error, 0.2);

            if ($probe !== false) {
                fclose($probe);

                return;
            }

            usleep(50_000);
        }

        $this->stop();

        throw new RuntimeException('O receptor de teste não subiu.');
    }

    /**
     * @param  list<string>  $secrets
     */
    public function expectSecrets(array $secrets): void
    {
        file_put_contents($this->secrets, implode("\n", $secrets));
    }

    /**
     * URL num nome qualquer (o DNS de mentira manda para 127.0.0.1).
     */
    public function url(string $path = '/ok', string $host = '127.0.0.1'): string
    {
        return 'http://'.$host.':'.$this->port.$path;
    }

    /**
     * @return list<array{method: string, path: string, headers: array<string, string>, body: string, verified_with: list<int>}>
     */
    public function requests(): array
    {
        $lines = array_filter(explode(PHP_EOL, (string) @file_get_contents($this->log)));

        return array_values(array_map(static fn (string $line): array => json_decode($line, true), $lines));
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
        @unlink($this->log);
        @unlink($this->secrets);
    }

    public function __destruct()
    {
        $this->stop();
    }
}
