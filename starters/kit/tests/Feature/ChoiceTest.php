<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;

// A escolha pelo ambiente (TWS_KIT_STACK, TWS_KIT_WITH, TWS_KIT_WITHOUT) — o
// caminho sem perguntas, igual no Linux, no macOS e no Windows.

it('sem variável nenhuma: o padrão seguro — Livewire, todos os módulos', function (): void {
    [$choice, $error] = envChoice([]);

    expect($error)->toBeNull()
        ->and($choice->stack)->toBe('livewire')
        ->and($choice->modules)->toBe(['accounts', 'uploads', 'admin'])
        ->and($choice->without())->toBe([]);
});

it('interface e módulos de fora, sem diferenciar maiúsculas e com espaços', function (): void {
    [$choice] = envChoice(['TWS_KIT_STACK' => ' React ', 'TWS_KIT_WITHOUT' => 'Uploads , admin']);

    expect($choice->stack)->toBe('react')
        ->and($choice->modules)->toBe(['accounts'])
        ->and($choice->without())->toBe(['uploads', 'admin']);
});

it('TWS_KIT_WITH aceita os opcionais e os obrigatórios (que já vêm)', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_WITH' => 'foundation,accounts', 'TWS_KIT_WITHOUT' => 'admin']);

    expect($error)->toBeNull()
        ->and($choice->modules)->toBe(['accounts', 'uploads']);
});

it('recusa uploads sem contas — e diz como corrigir as variáveis', function (): void {
    [$choice, $error] = envChoice(['TWS_KIT_WITHOUT' => 'accounts']);

    expect($choice)->toBeNull()
        ->and($error)->toBe('Secure uploads and profile photo (twstec/kit-uploads) needs Accounts with members, API keys and projects (twstec/kit-accounts). Leave both out (TWS_KIT_WITHOUT=accounts,uploads) or keep what is missing.');
});

it('reconhece quando há escolha no ambiente (mesmo vazia) — aí o menu não aparece', function (): void {
    expect(Choice::inEnvironment([]))->toBeFalse()
        ->and(Choice::inEnvironment(['TWS_KIT_WITHOUT' => false]))->toBeFalse()
        ->and(Choice::inEnvironment(['TWS_KIT_WITHOUT' => '']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_STACK' => 'react']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_NAME' => 'loja']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_SLOT' => '2']))->toBeTrue()
        ->and(Choice::inEnvironment(['TWS_KIT_EXPOSE_DB' => '1']))->toBeTrue()
        // A pasta (o compose do instalador informa) não é escolha.
        ->and(Choice::inEnvironment(['TWS_KIT_FOLDER' => 'x', 'TWS_KIT_IN_DOCKER' => '1']))->toBeFalse();
});
