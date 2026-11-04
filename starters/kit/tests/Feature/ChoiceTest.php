<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;

// A escolha pelo ambiente (TWS_KIT_STACK, TWS_KIT_WITH, TWS_KIT_WITHOUT) — o
// caminho sem perguntas, igual no Linux, no macOS e no Windows.

it('sem variável nenhuma: o padrão seguro — Livewire, todos os módulos', function (): void {
    [$choice, $error] = envChoice([]);

    expect($error)->toBeNull()
        ->and($choice->stack)->toBe('livewire')
        ->and($choice->modules)->toBe(['accounts', 'uploads', 'admin', 'webhooks'])
        ->and($choice->without())->toBe([])
        ->and($choice->database)->toBe('pgsql')
        ->and($choice->redis)->toBeTrue();
});

it('o banco e o Redis pelo ambiente: SQLite (e os sinônimos do PostgreSQL), Redis sim ou não', function (array $env, string $database, bool $redis): void {
    [$choice, $error] = envChoice($env);

    expect($error)->toBeNull()
        ->and($choice->database)->toBe($database)
        ->and($choice->redis)->toBe($redis)
        ->and($choice->toEnvironment())->toMatchArray(['TWS_KIT_DATABASE' => $database, 'TWS_KIT_REDIS' => $redis ? '1' : '0']);
})->with([
    'sqlite sem redis' => [['TWS_KIT_DATABASE' => ' SQLite ', 'TWS_KIT_REDIS' => '0'], 'sqlite', false],
    'postgres com redis' => [['TWS_KIT_DATABASE' => 'postgres', 'TWS_KIT_REDIS' => 'sim'], 'pgsql', true],
    'postgresql, redis não' => [['TWS_KIT_DATABASE' => 'postgresql', 'TWS_KIT_REDIS' => 'não'], 'pgsql', false],
    'vazios: o padrão' => [['TWS_KIT_DATABASE' => '', 'TWS_KIT_REDIS' => ''], 'pgsql', true],
]);

it('banco desconhecido (MySQL fica fora) e Redis inválido são recusados', function (array $env, string $message): void {
    [$choice, $error] = envChoice($env);

    expect($choice)->toBeNull()->and($error)->toBe($message);
})->with([
    [['TWS_KIT_DATABASE' => 'mysql'], 'Unknown database in TWS_KIT_DATABASE: mysql. Use pgsql or sqlite.'],
    [['TWS_KIT_REDIS' => 'talvez'], 'Invalid value in TWS_KIT_REDIS: talvez. Use 1 (with Redis) or 0.'],
]);

it('a escolha volta igual pelo ambiente, com o banco e o Redis', function (): void {
    [$choice] = envChoice(['TWS_KIT_DATABASE' => 'sqlite', 'TWS_KIT_REDIS' => '0', 'TWS_KIT_NAME' => 'loja', 'TWS_KIT_SLOT' => '3']);
    [$again] = envChoice($choice->toEnvironment());

    expect($again)->toEqual($choice);
});

it('interface e módulos de fora, sem diferenciar maiúsculas e com espaços', function (): void {
    [$choice] = envChoice(['TWS_KIT_STACK' => ' React ', 'TWS_KIT_WITHOUT' => 'Uploads , admin']);

    expect($choice->stack)->toBe('react')
        ->and($choice->modules)->toBe(['accounts', 'webhooks'])
        ->and($choice->without())->toBe(['uploads', 'admin']);
});

it('TWS_KIT_WITH aceita os opcionais e os obrigatórios (que já vêm)', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_WITH' => 'foundation,accounts', 'TWS_KIT_WITHOUT' => 'admin']);

    expect($error)->toBeNull()
        ->and($choice->modules)->toBe(['accounts', 'uploads', 'webhooks']);
});

it('recusa uploads sem contas — e diz como corrigir as variáveis, com tudo que depende de contas', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_WITHOUT' => 'accounts']);

    expect($choice)->toBeNull()
        ->and($error)->toBe('Secure uploads and profile photo (twstec/kit-uploads) needs Accounts with members, API keys and projects (twstec/kit-accounts). Leave out what depends on it too (TWS_KIT_WITHOUT=accounts,uploads,webhooks) or keep what is missing.');

    // A sugestão vale: seguida, não recusa de novo.
    [$corrigida, $semErro] = envChoice(['TWS_KIT_WITHOUT' => 'accounts,uploads,webhooks']);

    expect($semErro)->toBeNull()
        ->and($corrigida->modules)->toBe(['admin']);
});

it('recusa webhooks sem contas', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_WITHOUT' => 'accounts,uploads']);

    expect($choice)->toBeNull()
        ->and($error)->toBe('Signed outgoing webhooks (twstec/kit-webhooks) needs Accounts with members, API keys and projects (twstec/kit-accounts). Leave out what depends on it too (TWS_KIT_WITHOUT=accounts,uploads,webhooks) or keep what is missing.');
});

it('reconhece quando há escolha no ambiente (mesmo vazia) — aí o menu não aparece', function (): void {
    expect(Choice::inEnvironment([]))->toBeFalse()
        ->and(Choice::inEnvironment(['TWS_KIT_WITHOUT' => false]))->toBeFalse()
        ->and(Choice::inEnvironment(['TWS_KIT_WITHOUT' => '']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_STACK' => 'react']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_NAME' => 'loja']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_SLOT' => '2']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_EXPOSE_DB' => '1']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_DATABASE' => 'sqlite']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_REDIS' => '0']))->toBeTrue()
        // A pasta (o compose do instalador informa) não é escolha.
        ->and(Choice::inEnvironment(['TWS_KIT_FOLDER' => 'x', 'TWS_KIT_IN_DOCKER' => '1']))->toBeFalse();
});
