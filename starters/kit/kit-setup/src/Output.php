<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

/**
 * A saída do comando único DEPOIS do menu: texto simples (com cor só num
 * terminal). Não usa o Laravel Prompts de propósito — depois do
 * `composer update`, o vendor/ em disco já é o do projeto, e carregar dali
 * uma classe que este processo ainda não conhece misturaria versões.
 */
final class Output
{
    /**
     * @param  resource  $stream
     */
    public function __construct(private $stream, private readonly bool $colors) {}

    public static function stdout(): self
    {
        return new self(STDOUT, stream_isatty(STDOUT));
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stream, $text.PHP_EOL);
    }

    public function step(string $text): void
    {
        $this->line();
        $this->line($this->paint('1;36', '» '.$text));
    }

    public function info(string $text): void
    {
        $this->line($this->paint('32', $text));
    }

    public function warn(string $text): void
    {
        $this->line($this->paint('33', $text));
    }

    public function error(string $text): void
    {
        $this->line($this->paint('1;31', $text));
    }

    public function command(string $text): void
    {
        $this->line('    '.$this->paint('1', $text));
    }

    private function paint(string $code, string $text): string
    {
        return $this->colors ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
