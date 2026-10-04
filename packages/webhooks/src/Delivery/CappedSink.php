<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Destino do CORPO DA RESPOSTA do receptor (opção `sink` do Guzzle): guarda
 * só os primeiros `$limit` bytes e descarta o resto (sem acusar erro — o
 * status da resposta continua valendo). Um receptor que responda gigabytes
 * não enche a memória nem o disco do worker; o tempo total do envio continua
 * limitado pelo timeout.
 *
 * (A opção `stream` do Guzzle não serve: ela troca o handler pelo de
 * streams do PHP, que não aceita as opções de cURL — e sem elas não há
 * conexão no IP conferido.)
 */
final class CappedSink implements StreamInterface
{
    private string $buffer = '';

    private int $position = 0;

    private int $received = 0;

    public function __construct(private readonly int $limit) {}

    /**
     * Quantos bytes o receptor mandou (inclusive os descartados).
     */
    public function received(): int
    {
        return $this->received;
    }

    public function write(string $string): int
    {
        $length = strlen($string);
        $this->received += $length;
        $room = $this->limit - strlen($this->buffer);

        if ($room > 0) {
            $this->buffer .= substr($string, 0, $room);
        }

        return $length;
    }

    public function __toString(): string
    {
        return $this->buffer;
    }

    public function close(): void
    {
        $this->buffer = '';
        $this->position = 0;
    }

    public function detach()
    {
        $this->close();

        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->buffer);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->buffer);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $target = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->buffer) + $offset,
            default => throw new RuntimeException('whence inválido'),
        };

        if ($target < 0) {
            throw new RuntimeException('posição inválida');
        }

        $this->position = $target;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->buffer, $this->position, max(0, $length));
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $rest = substr($this->buffer, $this->position);
        $this->position = strlen($this->buffer);

        return $rest;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
