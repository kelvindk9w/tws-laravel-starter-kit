<?php

declare(strict_types=1);

use App\Core\Logging\Redactor;

// Redaction antes de persistir logs (ADR-004 — LGPD).

it('mascara chaves sensíveis por nome exato, case-insensitive', function (string $key) {
    $redacted = (new Redactor)->redactArray([$key => 'valor-super-secreto']);

    expect($redacted[$key])->toBe(Redactor::MASK);
})->with([
    'password', 'PASSWORD', 'Password', 'password_confirmation', 'current_password',
    'transaction_password', 'senha', 'token', 'access_token', 'refresh_token',
    'api_key', 'api_secret', 'secret', 'client_secret', 'authorization',
    'private_key', 'webhook_secret', 'card_number', 'cvv',
]);

it('mascara chaves sensíveis por sufixo', function (string $key) {
    $redacted = (new Redactor)->redactArray([$key => 'segredo']);

    expect($redacted[$key])->toBe(Redactor::MASK);
})->with(['webhook_token', 'hmac_secret', 'smtp_password', 'parceiro_api_key']);

it('não mascara chaves comuns', function () {
    $redacted = (new Redactor)->redactArray(['nome' => 'Maria', 'public_code' => 'CLI-9F4K2Q', 'monkey' => 'valor']);

    expect($redacted['nome'])->toBe('Maria')
        ->and($redacted['public_code'])->toBe('CLI-9F4K2Q')
        ->and($redacted['monkey'])->toBe('valor');
});

it('mascara recursivamente em arrays aninhados', function () {
    $redacted = (new Redactor)->redactArray([
        'usuario' => ['nome' => 'Maria', 'credenciais' => ['password' => '123456', 'perfil' => 'admin']],
    ]);

    expect($redacted['usuario']['credenciais']['password'])->toBe(Redactor::MASK)
        ->and($redacted['usuario']['credenciais']['perfil'])->toBe('admin')
        ->and($redacted['usuario']['nome'])->toBe('Maria');
});

it('mascara CPF parcialmente mantendo 3 primeiros e 2 últimos dígitos', function (string $input, string $expected) {
    expect((new Redactor)->redactString($input))->toBe($expected);
})->with([
    'com pontuação' => ['CPF: 123.456.789-09', 'CPF: 123.***.***-09'],
    'sem pontuação' => ['cpf 12345678909 ok', 'cpf 123******09 ok'],
]);

it('mascara CNPJ parcialmente', function () {
    expect((new Redactor)->redactString('CNPJ 12.345.678/0001-90'))->toBe('CNPJ 12.3**.***/****-90')
        ->and((new Redactor)->redactString('cnpj 12345678000190'))->toBe('cnpj 123*********90');
});

it('mascara e-mail mantendo primeira letra e domínio', function () {
    expect((new Redactor)->redactString('avise kelvin.silva@empresa.com.br'))->toBe('avise k***@empresa.com.br');
});

it('não confunde valores monetários ou ids com documentos', function () {
    expect((new Redactor)->redactString('valor 1990 centavos, pedido 12345'))->toBe('valor 1990 centavos, pedido 12345');
});

it('trunca strings muito longas (logs não são storage de payload)', function () {
    $redacted = (new Redactor)->redactArray(['descricao' => str_repeat('a', 5000)]);

    expect(mb_strlen($redacted['descricao']))->toBeLessThan(1100)
        ->and($redacted['descricao'])->toEndWith('…[truncado]');
});
