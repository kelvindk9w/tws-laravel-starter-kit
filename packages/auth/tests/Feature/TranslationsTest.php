<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

// As traduções do pacote: mesmas chaves nos três idiomas, e resolvidas pelos
// nomes de sempre (sem namespace), somadas às do aplicativo grupo a grupo.

const AUTH_LOCALES = ['pt_BR', 'en', 'es'];

function authLangPath(string $relative): string
{
    return dirname(__DIR__, 2).'/lang/'.$relative;
}

it('todos os idiomas têm os mesmos arquivos e chaves do pt-BR', function (): void {
    $reference = [];

    foreach (glob(authLangPath('pt_BR/*.php')) ?: [] as $file) {
        $reference[basename($file)] = collect(require $file)->dot()->keys()->sort()->values();
    }

    expect(array_keys($reference))->toBe(['auth.php', 'mail.php']);

    foreach (['en', 'es'] as $locale) {
        foreach ($reference as $file => $keys) {
            $path = authLangPath("{$locale}/{$file}");

            expect($path)->toBeFile();

            $actual = collect(require $path)->dot()->keys()->sort()->values();

            expect($actual->all())->toBe($keys->all(), "lang/{$locale}/{$file} diverge do pt-BR");
        }
    }
});

it('resolve cada chave do pacote pelo nome de sempre, nos três idiomas', function (string $locale): void {
    foreach (glob(authLangPath('pt_BR/*.php')) ?: [] as $file) {
        $group = basename($file, '.php');

        foreach (collect(require $file)->dot()->keys() as $key) {
            expect(app('translator')->hasForLocale("{$group}.{$key}", $locale))
                ->toBeTrue("Falta {$group}.{$key} em {$locale}");
        }
    }
})->with(AUTH_LOCALES);

it('toda chave que o código do pacote pede existe no pacote', function (): void {
    $faltando = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if (! str_ends_with((string) $file, '.php')) {
            continue;
        }

        preg_match_all("/__\\('((?:auth|mail)\\.[a-z_.]+[a-z_])'/", (string) file_get_contents((string) $file), $matches);

        foreach ($matches[1] as $key) {
            [$group, $item] = explode('.', $key, 2);

            foreach (AUTH_LOCALES as $locale) {
                // Direto no arquivo do PACOTE: a chave não pode depender do
                // lang/ do aplicativo nem das traduções do framework.
                if (! Arr::has(require authLangPath("{$locale}/{$group}.php"), $item)) {
                    $faltando[] = "{$key} ({$locale})";
                }
            }
        }
    }

    expect($faltando)->toBe([]);
});

it('a recusa da verificação em duas etapas numa conta protegida é neutra — vale para qualquer conta protegida', function (string $locale): void {
    // Quem protege uma conta é a extensão registrada em AccountProtection
    // (com a demonstração do kit instalada, ela dá o texto "de demo").
    $auth = require authLangPath("{$locale}/auth.php");

    expect(mb_strtolower($auth['two_factor']['account_protected']))->not->toContain('demo')
        // O nome antigo (até a 2.x) continua resolvendo, com o mesmo texto.
        ->and(__('auth.two_factor.demo_blocked', [], $locale))->toBe(__('auth.two_factor.account_protected', [], $locale));
})->with(['pt_BR', 'en', 'es']);
