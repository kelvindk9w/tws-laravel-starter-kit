<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

// =============================================================================
// REGRAS DO PRODUTO VALENDO PARA A DEMONSTRAÇÃO — as que só leem os arquivos
// do pacote. As regras de arquitetura do starter (AdminAuditTest,
// FormSubmissionsTest, LocaleTest…) varrem os diretórios do aplicativo; as
// telas, views e traduções da demo moram aqui e ficariam sem trava.
//
// Estes quatro testes moravam em starters/livewire/tests/Demo/Feature/
// Architecture/DemoBoundaryTest.php e vieram para cá SEM mudar asserção (só o
// caminho dos arquivos). O resto daquela fronteira — o que depende do painel,
// das telas e do build do aplicativo — continua lá.
// =============================================================================

function demoPackageRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Arquivos PHP das telas da demo no /admin (src/Filament).
 *
 * @return array<string, string> caminho relativo => conteúdo
 */
function demoFilamentSources(): array
{
    $sources = [];

    foreach ((new Finder)->files()->in(demoPackageRoot().'/src/Filament')->name('*.php') as $file) {
        $sources[Str::after($file->getRealPath(), demoPackageRoot().'/')] = $file->getContents();
    }

    ksort($sources);

    return $sources;
}

it('nenhuma escrita das telas da demo passa por fora dos eventos de model', function (): void {
    // As mesmas regras de AdminAuditTest, aplicadas a src/Filament.
    $proibidos = [
        '/\bDB::(?!transaction\()/' => 'DB:: (use o model; só DB::transaction é permitido)',
        '/Quietly\s*\(/' => '*Quietly() não dispara evento de model',
        '/withoutEvents\s*\(|withoutEventDispatcher/' => 'withoutEvents() desliga a captura',
        '/::truncate\s*\(|->truncate\s*\(/' => 'truncate()',
        '/->upsert\s*\(|::upsert\s*\(/' => 'upsert()',
        '/(?:->|::)insert(?:OrIgnore|GetId|Using)?\s*\(/' => 'insert() em massa',
        '/->(?:increment|decrement)(?:Each)?\s*\(/' => 'increment()/decrement() em massa',
    ];

    $violacoes = [];

    foreach (demoFilamentSources() as $path => $source) {
        foreach ($proibidos as $regex => $motivo) {
            if (preg_match($regex, $source) === 1) {
                $violacoes[] = "{$path}: {$motivo}";
            }
        }

        foreach (explode(';', $source) as $sentenca) {
            if (preg_match('/(?:::query\(\)|::where\w*\(|->where\w*\()/', $sentenca) === 1
                && preg_match('/->(?:update|delete|forceDelete)\s*\(/', $sentenca) === 1) {
                $violacoes[] = "{$path}: update/delete em massa numa consulta — ".trim(Str::limit($sentenca, 120));
            }
        }
    }

    expect($violacoes)->toBe([]);
});

it('toda recusa das telas da demo passa por AdminAudit::denied()', function (): void {
    $violacoes = [];

    foreach (demoFilamentSources() as $path => $source) {
        if (preg_match('/Notification::make\(\)[^;]*->danger\(\)/s', $source) === 1) {
            $violacoes[] = $path;
        }
    }

    expect($violacoes)->toBe([], 'Recusa montada à mão (Notification ...->danger()) não fica na trilha. Use AdminAudit::denied().');
});

it('as traduções da demo têm os mesmos arquivos e chaves nos três idiomas', function (): void {
    $reference = [];

    foreach (glob(demoPackageRoot().'/lang/pt_BR/*.php') as $file) {
        $reference[basename($file)] = collect(require $file)->dot()->keys()->sort()->values();
    }

    expect($reference)->not->toBeEmpty();

    foreach (['en', 'es'] as $locale) {
        foreach ($reference as $file => $keys) {
            $path = demoPackageRoot()."/lang/{$locale}/{$file}";

            expect($path)->toBeFile();

            $actual = collect(require $path)->dot()->keys()->sort()->values();

            expect($actual->all())->toBe($keys->all(), "lang/{$locale}/{$file} diverge do pt-BR");
        }
    }
});

it('nenhuma view da demo exibe submissão com echo cru ({!! !!})', function (): void {
    $views = iterator_to_array((new Finder)->files()->in(demoPackageRoot().'/resources/views')->name('*.blade.php'));

    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        expect(preg_match('/\{!!.*(message|nickname|submission)/i', $view->getContents()))
            ->toBe(0, "Echo cru de campo de submissão em {$view->getRealPath()}");
    }
});
