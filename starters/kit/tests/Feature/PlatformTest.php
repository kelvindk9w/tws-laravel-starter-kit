<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Platform\PlatformRequirements;

// =============================================================================
// AS EXTENSÕES DO PHP nas chamadas ao Composer: o que a pessoa pediu para
// ignorar chega ao Composer de dentro; no Windows, só `ext-pcntl` e
// `ext-posix` (o Horizon) são ignoradas; qualquer outra que falte para a
// instalação com a lista e as duas saídas. (O projeto e o Composer de mentira:
// ver CreateProjectTest.)
// =============================================================================

it('o que a pessoa pediu: as variáveis e as opções do create-project', function (array $env, array $argv, array $expected): void {
    expect(PlatformRequirements::requested($env, $argv))->toBe($expected);
})->with([
    'nada' => [[], [], [false, []]],
    'variável com lista' => [['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-gd, EXT-Intl'], [], [false, ['ext-gd', 'ext-intl']]],
    'variável de tudo' => [['COMPOSER_IGNORE_PLATFORM_REQS' => '1'], [], [true, []]],
    'opção com =' => [[], ['composer', 'create-project', 'twstec/kit', 'x', '--ignore-platform-req=ext-bcmath'], [false, ['ext-bcmath']]],
    'opção separada' => [[], ['create-project', '--ignore-platform-req', 'ext-zip'], [false, ['ext-zip']]],
    'opção de tudo' => [[], ['create-project', '--ignore-platform-reqs'], [true, []]],
    'as duas somam' => [['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-gd'], ['--ignore-platform-req=ext-gd,ext-intl'], [false, ['ext-gd', 'ext-intl']]],
]);

it('o ambiente do Composer de dentro: no Windows, SÓ pcntl e posix; fora dele, só o que foi pedido', function (string $os, array $env, array $argv, array $expected): void {
    expect(PlatformRequirements::composerEnvironment($env, $os, $argv))->toBe($expected);
})->with([
    'Linux, nada' => ['Linux', [], [], []],
    'macOS, nada' => ['Darwin', [], [], []],
    'Windows' => ['Windows', [], [], ['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-pcntl,ext-posix']],
    'Windows + o pedido' => ['Windows', ['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-gd'], [], ['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-gd,ext-pcntl,ext-posix']],
    'Linux + opção' => ['Linux', [], ['--ignore-platform-req=ext-bcmath'], ['COMPOSER_IGNORE_PLATFORM_REQ' => 'ext-bcmath']],
    'tudo vence' => ['Windows', [], ['--ignore-platform-reqs'], ['COMPOSER_IGNORE_PLATFORM_REQS' => '1']],
]);

it('lê as extensões que faltam na mensagem do Composer, sem repetir', function (): void {
    expect(PlatformRequirements::missingExtensions(MISSING_EXTENSIONS_OUTPUT))->toBe(['bcmath', 'gd'])
        ->and(PlatformRequirements::missingExtensions('Nothing to install, update or remove'))->toBe([]);
});

it('o comando de quem chamou: fora do Linux (sem /proc), nada', function (): void {
    expect(PlatformRequirements::callerArguments())->toBeArray();
});
