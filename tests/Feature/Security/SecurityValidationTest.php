<?php

declare(strict_types=1);

use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use Illuminate\Support\Facades\Route;

// Validação de segurança global (ADR-005): payload malicioso é bloqueado,
// registrado SANITIZADO/escapado (nunca executável) + metadados, e a
// resposta não revela o que foi detectado.

beforeEach(function () {
    Route::post('/api/_test/echo', fn () => response()->json(['ok' => true]));
});

it('bloqueia payload XSS com 422, sem devolver script executável', function () {
    $response = $this->postJson('/api/_test/echo', ['comment' => '<script>alert(1)</script>']);

    $response->assertUnprocessable();
    $response->assertHeader('X-Correlation-Id');

    // Resposta genérica: não revela o tipo de ataque nem ecoa o payload.
    expect($response->getContent())->not->toContain('<script>')
        ->and($response->json('message'))->not->toContain('xss');
});

it('registra a tentativa XSS sanitizada/escapada com metadados (ADR-005)', function () {
    $this->postJson('/api/_test/echo', ['comment' => '<script>alert(1)</script>']);

    $log = RequestLog::query()->sole();

    expect($log->status)->toBe(RequestLogStatus::Bloqueada)
        ->and($log->attack_type)->toBe('xss')
        ->and($log->http_status_response)->toBe(422)
        ->and($log->ip)->not->toBeNull()
        ->and($log->endpoint)->toContain('api/_test/echo')
        // Payload persistido NUNCA executável:
        ->and($log->payload['comment'])->toContain('&lt;script&gt;')
        ->and($log->payload['comment'])->not->toContain('<script>');
});

it('bloqueia SQL injection e registra o tipo do ataque', function (string $payload) {
    $this->postJson('/api/_test/echo', ['filtro' => $payload])->assertUnprocessable();

    expect(RequestLog::query()->sole()->attack_type)->toBe('sqli');
})->with([
    'union select' => ['1 UNION SELECT password FROM users'],
    'tautologia' => ["1' OR '1'='1"],
    'drop table' => ['x; DROP TABLE users'],
]);

it('bloqueia null byte e path traversal', function (string $payload, string $tipo) {
    $this->postJson('/api/_test/echo', ['arquivo' => $payload])->assertUnprocessable();

    expect(RequestLog::query()->sole()->attack_type)->toBe($tipo);
})->with([
    'null byte' => ["foto\0.php", 'null_byte'],
    'path traversal' => ['../../etc/passwd', 'path_traversal'],
]);

it('aplica redaction também no payload malicioso persistido (dupla camada)', function () {
    $this->postJson('/api/_test/echo', [
        'comment' => '<script>alert(1)</script>',
        'password' => 'senha-super-secreta',
        'cpf' => '123.456.789-09',
    ]);

    $payload = RequestLog::query()->sole()->payload;

    expect($payload['password'])->toBe('[REDACTED]')
        ->and($payload['cpf'])->toBe('123.***.***-09')
        ->and(json_encode($payload))->not->toContain('senha-super-secreta');
});

it('deixa passar payload limpo sem marcar ataque', function () {
    $response = $this->postJson('/api/_test/echo', [
        'nome' => 'Maria Silva',
        'mensagem' => 'Atenção: cobrança não paga será cancelada!',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    expect(RequestLog::query()->sole()->attack_type)->toBeNull();
});
