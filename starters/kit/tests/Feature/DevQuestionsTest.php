<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Twstec\Kit\Setup\Tests\Fixtures\FakeHost;

// =============================================================================
// AS DUAS PRIMEIRAS PERGUNTAS DO MENU — o nome e o número do projeto (o Docker
// de desenvolvimento). Cada uma vem com a resposta sugerida (Enter aceita);
// o nome de um projeto Docker que já existe e o número com porta ocupada são
// recusados, dizendo por quê e sugerindo outro, e a pergunta continua aberta.
// (menu(), fakeKeys() e ACCEPT_NAME_AND_SLOT: ver MenuTest.)
// =============================================================================

/**
 * Apaga o que está no campo e digita o texto.
 *
 * @return list<string>
 */
function retype(string $text, int $erase = 20): array
{
    return [...array_fill(0, $erase, Key::BACKSPACE), ...mb_str_split($text)];
}

/**
 * Enter nas três perguntas do fim (interface, módulos, confirmação).
 */
const ACCEPT_REST = [Key::ENTER, Key::ENTER, Key::ENTER];

it('Enter aceita as sugestões: o nome da pasta sem colidir e o primeiro número livre — e mostra quem usa os outros', function (): void {
    $host = (new FakeHost)->withProject('meu-app', 0);
    fakeKeys([...ACCEPT_NAME_AND_SLOT, ...ACCEPT_REST]);

    $choice = menu(host: $host)->ask();

    expect($choice->name)->toBe('meu-app-2')
        ->and($choice->slot)->toBe(1);

    Prompt::assertStrippedOutputContains('0 is already used by meu-app');
    Prompt::assertStrippedOutputContains('Project: meu-app-2 — http://meu-app-2.localhost:8081 (e-mails: http://meu-app-2.localhost:8021)');
});

it('o nome de um projeto Docker que já existe é recusado, com a sugestão; outro nome segue', function (): void {
    $host = (new FakeHost)->withProject('loja', 0);
    fakeKeys([
        ...retype('loja'), Key::ENTER,       // recusado: já existe
        ...retype('loja-nova'), Key::ENTER,  // vale
        Key::ENTER,                          // número sugerido
        ...ACCEPT_REST,
    ]);

    $choice = menu(host: $host)->ask();

    Prompt::assertStrippedOutputContains('there is already a Docker project called loja on this machine');
    Prompt::assertStrippedOutputContains('suggestion: loja-2');

    expect($choice->name)->toBe('loja-nova');
});

it('o nome digitado vira nome válido (Loja da Maria → loja-da-maria)', function (): void {
    fakeKeys([...retype('Loja da Maria'), Key::ENTER, Key::ENTER, ...ACCEPT_REST]);

    expect(menu()->ask()->name)->toBe('loja-da-maria');
});

it('número com porta ocupada é recusado — quem ocupa e o primeiro livre —; número inválido também', function (): void {
    $host = (new FakeHost)->withProject('loja-da-maria', 0);
    $host->busy = [8032];
    fakeKeys([
        Key::ENTER,                   // nome sugerido
        ...retype('0'), Key::ENTER,   // recusado: loja-da-maria
        ...retype('2'), Key::ENTER,   // recusado: o Vite (8032) ocupado por outro programa
        ...retype('x'), Key::ENTER,   // recusado: não é número
        ...retype('3'), Key::ENTER,   // vale
        ...ACCEPT_REST,
    ]);

    $choice = menu(host: $host)->ask();

    Prompt::assertStrippedOutputContains('number 0 is in use — loja-da-maria (8020, 8030, 8080). The first number with all four ports free is 1.');
    Prompt::assertStrippedOutputContains('number 2 is in use — another program (8032).');
    Prompt::assertStrippedOutputContains('Type a number from 0 to 99.');

    expect($choice->slot)->toBe(3);
});

it('a regra do número, sozinha: nenhuma porta ocupada passa', function (): void {
    $host = new FakeHost(busy: [8045]);
    $menu = menu(host: $host);

    expect($menu->validateSlot('5'))->toBe('number 5 is in use — another program (8045). The first number with all four ports free is 0.')
        ->and($menu->validateSlot('0'))->toBeNull()
        ->and($menu->validateSlot('100'))->toBe('Type a number from 0 to 99.');
});

it('0 a 9 ocupados: a sugestão é da centena seguinte, e o menu explica', function (): void {
    $host = new FakeHost;

    for ($slot = 0; $slot <= 9; $slot++) {
        $host->withProject("p{$slot}", $slot);
    }

    fakeKeys([...ACCEPT_NAME_AND_SLOT, ...ACCEPT_REST]);

    expect(menu(host: $host)->ask()->slot)->toBe(10);

    Prompt::assertStrippedOutputContains('the suggestion is 10, in the next hundred');
    Prompt::assertStrippedOutputContains('Project: meu-app — http://meu-app.localhost:8180');
});

it('sem o Docker, avisa que os outros projetos não podem ser conferidos — e segue', function (): void {
    fakeKeys([...ACCEPT_NAME_AND_SLOT, ...ACCEPT_REST]);

    expect(menu(host: new FakeHost(available: false))->ask()->name)->toBe('meu-app');

    Prompt::assertStrippedOutputContains('Docker did not respond from here');
});
