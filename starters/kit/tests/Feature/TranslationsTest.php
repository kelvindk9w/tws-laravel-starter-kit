<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Translator;

// Os textos do menu em pt_BR, en e es: as mesmas chaves e os mesmos
// marcadores nos três; o idioma vem do ambiente.

function flatten(array $lines, string $prefix = ''): array
{
    $flat = [];

    foreach ($lines as $key => $value) {
        is_array($value)
            ? $flat += flatten($value, $prefix.$key.'.')
            : $flat[$prefix.$key] = $value;
    }

    return $flat;
}

it('as mesmas chaves e os mesmos marcadores nos três idiomas', function (): void {
    $path = dirname(__DIR__, 2).'/kit-setup/lang';
    $base = flatten(require $path.'/pt_BR.php');

    foreach (Translator::LOCALES as $locale) {
        $lines = flatten(require $path."/{$locale}.php");

        expect(array_keys($lines))->toBe(array_keys($base), "chaves de {$locale}");

        foreach ($lines as $key => $text) {
            preg_match_all('/:[a-z]+/', $text, $found);
            preg_match_all('/:[a-z]+/', $base[$key], $expected);
            sort($found[0]);
            sort($expected[0]);

            expect($found[0])->toBe($expected[0], "marcadores de {$locale}.{$key}");
        }
    }
});

it('toda chave usada pelo código existe', function (): void {
    $source = '';

    foreach (glob(dirname(__DIR__, 2).'/kit-setup/src/*.php') as $file) {
        $source .= file_get_contents($file);
    }

    preg_match_all("/(?:->get|t->get)\\('([a-z_.]+)'/", $source, $keys);
    $lines = flatten(require dirname(__DIR__, 2).'/kit-setup/lang/pt_BR.php');

    foreach (array_unique($keys[1]) as $key) {
        expect($lines)->toHaveKey($key);
    }
});

it('o idioma: TWS_KIT_LOCALE, depois o do sistema; senão, pt_BR', function (array $env, string $locale): void {
    expect(Translator::detect($env))->toBe($locale);
})->with([
    'nada' => [[], 'pt_BR'],
    'variável do kit' => [['TWS_KIT_LOCALE' => 'es', 'LANG' => 'en_US.UTF-8'], 'es'],
    'LANG en' => [['LANG' => 'en_US.UTF-8'], 'en'],
    'LANG pt' => [['LANG' => 'pt_BR.UTF-8'], 'pt_BR'],
    'LC_ALL vence LANG' => [['LC_ALL' => 'es_ES.UTF-8', 'LANG' => 'en_US.UTF-8'], 'es'],
    'C.UTF-8 não conta' => [['LANG' => 'C.UTF-8'], 'pt_BR'],
    'idioma sem tradução' => [['LANG' => 'de_DE.UTF-8'], 'pt_BR'],
]);

it('troca os marcadores sem confundir :module com :modules', function (): void {
    expect(translator()->get('errors.unknown_module', ['variable' => 'X', 'module' => 'm', 'modules' => 'a, b']))
        ->toBe('Unknown module in X: m. The optional ones are: a, b.');
});
