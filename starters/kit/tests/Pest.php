<?php

declare(strict_types=1);

use Laravel\Prompts\Prompt;
use Twstec\Kit\Setup\Choice;
use Twstec\Kit\Setup\Tests\Fixtures\FakeHost;
use Twstec\Kit\Setup\Translator;

// A suíte do comando único não sobe aplicação nenhuma: é PHP puro, o Laravel
// Prompts com teclas de mentira e um Composer de mentira (Fixtures).

/**
 * Os textos, em inglês (as asserções ficam legíveis em qualquer máquina).
 */
function translator(string $locale = 'en'): Translator
{
    return new Translator($locale, dirname(__DIR__).'/kit-setup/lang');
}

/**
 * A escolha pelo ambiente, na pasta "meu-app", numa máquina de mentira (vazia,
 * salvo a pedida).
 *
 * @return array{0: ?Choice, 1: ?string}
 */
function envChoice(array $env, ?FakeHost $host = null): array
{
    return Choice::fromEnvironment($env, ['livewire', 'react'], 'livewire', translator(), $host ?? new FakeHost, 'meu-app');
}

/**
 * Uma pasta temporária nova.
 */
function temporaryDirectory(): string
{
    $directory = sys_get_temp_dir().'/twstec-kit-'.bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

function removeDirectory(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
        removeDirectory($path.'/'.$entry);
    }

    rmdir($path);
}

afterEach(function (): void {
    // O menu registra o que fazer no Ctrl+C e liga o modo "reserva"
    // (Windows); cada teste começa do zero.
    Prompt::cancelUsing(null);
    (new ReflectionProperty(Prompt::class, 'shouldFallback'))->setValue(null, false);
});
