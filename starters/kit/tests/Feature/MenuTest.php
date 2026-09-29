<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Twstec\Kit\Setup\Menu;

// =============================================================================
// O MENU (Laravel Prompts) com as teclas de mentira do próprio Prompts: a
// interface, os módulos e a confirmação. E a regra que ele não deixa passar:
// Uploads marcado com Contas desmarcado.
// =============================================================================

/**
 * As teclas de mentira num terminal largo: a mensagem da recusa cabe numa
 * linha só (num de 80 colunas, a moldura a quebraria).
 */
function fakeKeys(array $keys): void
{
    Prompt::fake($keys);
    Prompt::terminal()->shouldReceive('cols')->andReturn(240);
}

function menu(?bool $fallback = null, $input = null, $output = null): Menu
{
    return new Menu(translator(), ['livewire' => 'twstec/starter-livewire', 'react' => 'twstec/starter-react'], 'livewire', $fallback, $input, $output);
}

it('Enter, Enter, Enter: Livewire com todos os módulos', function (): void {
    fakeKeys([Key::ENTER, Key::ENTER, Key::ENTER]);

    $choice = menu(false)->ask();

    expect($choice->stack)->toBe('livewire')
        ->and($choice->modules)->toBe(['accounts', 'uploads', 'admin']);

    Prompt::assertStrippedOutputContains('Which interface?');
    Prompt::assertStrippedOutputContains('Always included: Foundation');
    Prompt::assertStrippedOutputContains('Which optional modules?');
    Prompt::assertStrippedOutputContains('Create the project like this?');
});

it('React, sem uploads: seta, espaço no segundo módulo, e confirma', function (): void {
    fakeKeys([Key::DOWN, Key::ENTER, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER]);

    $choice = menu(false)->ask();

    expect($choice->stack)->toBe('react')
        ->and($choice->modules)->toBe(['accounts', 'admin']);

    Prompt::assertStrippedOutputContains('Optional modules: Accounts with members, API keys and projects (twstec/kit-accounts), /admin panel with Filament (twstec/kit-admin)');
});

it('IMPEDE uploads sem contas: o Enter mostra o motivo e a pergunta continua até a escolha valer', function (): void {
    fakeKeys([
        Key::ENTER,          // Livewire
        Key::SPACE,          // desmarca Contas (Uploads continua marcado)
        Key::ENTER,          // tenta seguir: recusado
        Key::SPACE,          // marca Contas de novo
        Key::ENTER,          // agora vale
        Key::ENTER,          // confirma
    ]);

    $choice = menu(false)->ask();

    Prompt::assertStrippedOutputContains('Secure uploads and profile photo (twstec/kit-uploads) needs Accounts with members, API keys and projects (twstec/kit-accounts).');

    expect($choice->modules)->toBe(['accounts', 'uploads', 'admin']);
});

it('desmarcar contas e uploads juntos vale (só a base e o /admin)', function (): void {
    fakeKeys([Key::ENTER, Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER]);

    expect(menu(false)->ask()->modules)->toBe(['admin']);

    Prompt::assertStrippedOutputDoesntContain('(twstec/kit-uploads) needs Accounts');
});

it('a regra do menu, sozinha', function (array $values, ?string $error): void {
    expect(menu(false)->validate($values))->toBe($error);
})->with([
    'tudo' => [['accounts', 'uploads', 'admin'], null],
    'nada' => [[], null],
    'uploads sem contas' => [['uploads'], 'Secure uploads and profile photo (twstec/kit-uploads) needs Accounts with members, API keys and projects (twstec/kit-accounts).'],
    'uploads e admin sem contas' => [['uploads', 'admin'], 'Secure uploads and profile photo (twstec/kit-uploads) needs Accounts with members, API keys and projects (twstec/kit-accounts).'],
]);

it('recusar a confirmação desiste (null)', function (): void {
    fakeKeys([Key::ENTER, Key::ENTER, 'n', Key::ENTER]);

    expect(menu(false)->ask())->toBeNull();
});

it('Ctrl+C desiste sem derrubar o processo (null)', function (): void {
    fakeKeys([Key::CTRL_C]);

    expect(menu(false)->ask())->toBeNull();
});

it('Windows (sem stty): as mesmas perguntas em listas numeradas, com a mesma recusa de uploads sem contas', function (): void {
    $input = new ArrayInput([]);
    // Interface 2 (React); módulos só "2" (Uploads, sem Contas) → recusado;
    // de novo "0,1" (Contas e Uploads); confirma.
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "1\n1\n0,1\ny\n");
    rewind($stream);
    $input->setStream($stream);
    $output = new BufferedOutput;

    Prompt::interactive(true);
    $choice = menu(true, $input, $output)->ask();

    expect($choice->stack)->toBe('react')
        ->and($choice->modules)->toBe(['accounts', 'uploads'])
        ->and($output->fetch())->toContain('Which interface?')
        ->toContain('needs Accounts with members');
});
