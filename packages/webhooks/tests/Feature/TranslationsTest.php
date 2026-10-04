<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Twstec\Kit\Webhooks\Enums\AttemptOutcome;
use Twstec\Kit\Webhooks\Enums\DeliveryStatus;
use Twstec\Kit\Webhooks\Enums\DisabledReason;
use Twstec\Kit\Webhooks\Enums\EndpointStatus;

// As traduções do pacote: mesmas chaves nos três idiomas, e resolvidas pelos
// nomes de sempre (sem namespace), somadas às do aplicativo grupo a grupo.

const WEBHOOKS_LOCALES = ['pt_BR', 'en', 'es'];

function webhooksLangPath(string $relative): string
{
    return dirname(__DIR__, 2).'/lang/'.$relative;
}

it('todos os idiomas têm os mesmos arquivos e chaves do pt-BR', function (): void {
    $reference = [];

    foreach (glob(webhooksLangPath('pt_BR/*.php')) ?: [] as $file) {
        $reference[basename($file)] = collect(require $file)->dot()->keys()->sort()->values();
    }

    expect(array_keys($reference))->toBe(['webhooks.php']);

    foreach (['en', 'es'] as $locale) {
        foreach ($reference as $file => $keys) {
            $path = webhooksLangPath("{$locale}/{$file}");

            expect($path)->toBeFile();

            $actual = collect(require $path)->dot()->keys()->sort()->values();

            expect($actual->all())->toBe($keys->all(), "lang/{$locale}/{$file} diverge do pt-BR");
        }
    }
});

it('resolve cada chave do pacote pelo nome de sempre, nos três idiomas', function (string $locale): void {
    foreach (glob(webhooksLangPath('pt_BR/*.php')) ?: [] as $file) {
        $group = basename($file, '.php');

        foreach (collect(require $file)->dot()->keys() as $key) {
            expect(app('translator')->hasForLocale("{$group}.{$key}", $locale))
                ->toBeTrue("Falta {$group}.{$key} em {$locale}");
        }
    }
})->with(WEBHOOKS_LOCALES);

it('toda chave que o código do pacote pede existe no pacote — inclusive cada motivo de recusa', function (): void {
    $faltando = [];
    $pedidas = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src')) as $file) {
        if (! str_ends_with((string) $file, '.php')) {
            continue;
        }

        $codigo = (string) file_get_contents((string) $file);

        preg_match_all("/__\\('(webhooks\\.[a-z_.]+[a-z_])'/", $codigo, $matches);
        array_push($pedidas, ...$matches[1]);

        // Os motivos de recusa de destino entram por concatenação
        // ('webhooks.destination.'.$reason): cada `BlockedDestinationException('motivo'`
        // do código é uma chave.
        preg_match_all("/BlockedDestinationException\\('([a-z_]+)'/", $codigo, $motivos);

        foreach ($motivos[1] as $motivo) {
            $pedidas[] = 'webhooks.destination.'.$motivo;
        }
    }

    // Os rótulos dos enums (`webhooks.<grupo>.<valor>`).
    foreach ([
        'status' => EndpointStatus::cases(),
        'disabled_reason' => DisabledReason::cases(),
        'delivery_status' => DeliveryStatus::cases(),
        'attempt_outcome' => AttemptOutcome::cases(),
    ] as $group => $cases) {
        foreach ($cases as $case) {
            $pedidas[] = "webhooks.{$group}.{$case->value}";
        }
    }

    $pedidas = array_values(array_unique($pedidas));

    foreach ($pedidas as $key) {
        [$group, $item] = explode('.', $key, 2);

        foreach (WEBHOOKS_LOCALES as $locale) {
            // Direto no arquivo do PACOTE: a chave não pode depender do lang/
            // do aplicativo.
            if (! Arr::has(require webhooksLangPath("{$locale}/{$group}.php"), $item)) {
                $faltando[] = "{$key} ({$locale})";
            }
        }
    }

    expect(count($pedidas))->toBeGreaterThan(30)
        ->and($faltando)->toBe([]);
});
