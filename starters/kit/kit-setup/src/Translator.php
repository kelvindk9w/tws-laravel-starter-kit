<?php

declare(strict_types=1);

namespace Twstec\Kit\Setup;

/**
 * Os textos do comando único em pt_BR, en e es (kit-setup/lang).
 *
 * O idioma vem de TWS_KIT_LOCALE; sem ela, do idioma do sistema (LC_ALL,
 * LC_MESSAGES, LANG) quando é um dos três; senão, pt_BR — o mesmo padrão do
 * APP_LOCALE dos starters, para o menu e o instalador do projeto falarem a
 * mesma língua.
 */
final class Translator
{
    /**
     * @var list<string>
     */
    public const LOCALES = ['pt_BR', 'en', 'es'];

    public const DEFAULT_LOCALE = 'pt_BR';

    /**
     * @var array<string, mixed>
     */
    private array $lines;

    public function __construct(public readonly string $locale, string $langPath)
    {
        /** @var array<string, mixed> $lines */
        $lines = require $langPath.'/'.$locale.'.php';
        $this->lines = $lines;
    }

    /**
     * @param  array<string, string|false>  $env
     */
    public static function detect(array $env): string
    {
        foreach (['TWS_KIT_LOCALE', 'LC_ALL', 'LC_MESSAGES', 'LANG'] as $variable) {
            $value = strtolower(trim((string) ($env[$variable] ?? '')));

            if ($value === '' || $value === 'c' || $value === 'posix' || str_starts_with($value, 'c.')) {
                continue;
            }

            $locale = match (true) {
                str_starts_with($value, 'pt') => 'pt_BR',
                str_starts_with($value, 'es') => 'es',
                str_starts_with($value, 'en') => 'en',
                default => null,
            };

            if ($locale !== null) {
                return $locale;
            }
        }

        return self::DEFAULT_LOCALE;
    }

    /**
     * A chave existe (e é um texto)?
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== $key;
    }

    /**
     * @param  array<string, string>  $replace
     */
    public function get(string $key, array $replace = []): string
    {
        $value = $this->lines;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $key;
            }

            $value = $value[$segment];
        }

        if (! is_string($value)) {
            return $key;
        }

        // As chaves mais longas primeiro: `:modules` não pode ser trocado
        // como `:module` + "s".
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($replace as $name => $text) {
            $value = str_replace(':'.$name, $text, $value);
        }

        return $value;
    }

    /**
     * Os nomes (traduzidos) de uma lista de módulos, separados por vírgula.
     *
     * @param  list<string>  $modules
     */
    public function modules(array $modules): string
    {
        return implode(', ', array_map(fn (string $m): string => $this->get("modules.{$m}"), $modules));
    }
}
