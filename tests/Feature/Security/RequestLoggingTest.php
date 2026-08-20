<?php

declare(strict_types=1);

use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Exceptions\AppendOnlyViolationException;
use App\Core\Logging\Models\RequestLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Pipeline de logs de requisição (ADR-004/010): INICIADA imediato →
// CONCLUIDA/ERRO no terminate, correlation_id propagado, append-only.

beforeEach(function () {
    Route::post('/api/_test/echo', fn () => response()->json(['ok' => true]));
});

it('registra INICIADA→CONCLUÍDA e propaga o correlation_id na resposta', function () {
    $response = $this->postJson('/api/_test/echo', ['nome' => 'Kelvin']);

    $response->assertOk();
    $response->assertHeader('X-Correlation-Id');

    $correlationId = $response->headers->get('X-Correlation-Id');

    expect(Str::isUuid($correlationId))->toBeTrue();

    $log = RequestLog::query()->where('correlation_id', $correlationId)->sole();

    expect($log->status)->toBe(RequestLogStatus::Concluida)
        ->and($log->http_status_response)->toBe(200)
        ->and($log->duration_ms)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($log->method)->toBe('POST')
        ->and($log->endpoint)->toBe('api/_test/echo')
        ->and($log->payload['nome'])->toBe('Kelvin')
        ->and($log->tenant_uuid)->toBeNull(); // tenancy chega na Fase 4
});

it('reutiliza X-Correlation-Id de entrada quando é um UUID válido', function () {
    $id = (string) Str::uuid7();

    $response = $this->postJson('/api/_test/echo', ['nome' => 'x'], ['X-Correlation-Id' => $id]);

    expect($response->headers->get('X-Correlation-Id'))->toBe($id);
});

it('descarta X-Correlation-Id de entrada inválido e gera um novo', function () {
    $response = $this->postJson('/api/_test/echo', ['nome' => 'x'], ['X-Correlation-Id' => 'nao-e-uuid']);

    $correlationId = $response->headers->get('X-Correlation-Id');

    expect($correlationId)->not->toBe('nao-e-uuid')
        ->and(Str::isUuid($correlationId))->toBeTrue();
});

it('registra requisição para endpoint inexistente (sinal de varredura — ADR-010)', function () {
    $response = $this->get('/api/endpoint-que-nao-existe');

    $response->assertNotFound();

    $log = RequestLog::query()->sole();

    expect($log->status)->toBe(RequestLogStatus::Concluida)
        ->and($log->http_status_response)->toBe(404)
        ->and($log->endpoint)->toBe('api/endpoint-que-nao-existe')
        ->and($log->tenant_uuid)->toBeNull();
});

it('não registra rotas web (log pesado é da API nesta fase)', function () {
    $this->get('/')->assertOk();

    expect(RequestLog::query()->count())->toBe(0);
});

it('marca ERRO com a mensagem quando a rota lança exceção', function () {
    Route::get('/api/_test/boom', function () {
        throw new RuntimeException('falha proposital do teste');
    });

    $response = $this->get('/api/_test/boom');

    $response->assertServerError();

    $log = RequestLog::query()->where('endpoint', 'api/_test/boom')->sole();

    expect($log->status)->toBe(RequestLogStatus::Erro)
        ->and($log->http_status_response)->toBe(500)
        ->and($log->error_message)->toContain('falha proposital do teste')
        ->and($log->duration_ms)->not->toBeNull();
});

it('mascara segredos, CPF e e-mail antes de persistir (LGPD — ADR-004)', function () {
    $this->postJson('/api/_test/echo', [
        'password' => 'senha-super-secreta',
        'webhook_token' => 'tok_live_123456',
        'cpf' => '123.456.789-09',
        'contato' => 'kelvin@example.com',
        'aninhado' => ['client_secret' => 'shhh-secret'],
    ])->assertOk();

    $payload = RequestLog::query()->sole()->payload;

    expect($payload['password'])->toBe('[REDACTED]')
        ->and($payload['webhook_token'])->toBe('[REDACTED]')
        ->and($payload['aninhado']['client_secret'])->toBe('[REDACTED]')
        ->and($payload['cpf'])->toBe('123.***.***-09')
        ->and($payload['contato'])->toBe('k***@example.com');

    // Garantia no nível do JSON cru persistido (conteúdo, não só cast).
    $raw = (string) DB::table('request_logs')->value('payload');

    expect($raw)->not->toContain('senha-super-secreta')
        ->and($raw)->not->toContain('tok_live_123456')
        ->and($raw)->not->toContain('shhh-secret')
        ->and($raw)->not->toContain('456.789')
        ->and($raw)->not->toContain('kelvin@');
});

it('request_logs é append-only: update e delete via Eloquent lançam exceção', function () {
    $this->postJson('/api/_test/echo', ['nome' => 'x']);

    $log = RequestLog::query()->sole();

    // Nota: o update precisa alterar um atributo de fato — Eloquent não
    // dispara save/eventos quando não há nada "sujo".
    expect(fn () => $log->update(['endpoint' => 'api/adulterado']))
        ->toThrow(AppendOnlyViolationException::class);

    expect(fn () => $log->delete())
        ->toThrow(AppendOnlyViolationException::class);

    expect(fn () => RequestLog::query()->sole()->forceFill(['ip' => '1.2.3.4'])->save())
        ->toThrow(AppendOnlyViolationException::class);
});

it('permite as transições controladas: markFinished e bindTenant', function () {
    $log = RequestLog::query()->create([
        'correlation_id' => (string) Str::uuid7(),
        'ip' => '127.0.0.1',
        'method' => 'GET',
        'endpoint' => 'api/_test/manual',
        'status' => RequestLogStatus::Iniciada,
    ]);

    $log->markFinished(RequestLogStatus::Concluida, 200, null, 42);

    expect($log->fresh()->status)->toBe(RequestLogStatus::Concluida)
        ->and($log->fresh()->duration_ms)->toBe(42);

    $tenantUuid = (string) Str::uuid7();

    expect(RequestLog::bindTenantByCorrelationId($log->correlation_id, $tenantUuid))->toBeTrue()
        ->and($log->fresh()->tenant_uuid)->toBe($tenantUuid)
        ->and(RequestLog::bindTenantByCorrelationId((string) Str::uuid7(), $tenantUuid))->toBeFalse();
});

it('markFinished recusa status fora do ciclo de vida', function () {
    $log = RequestLog::query()->create([
        'correlation_id' => (string) Str::uuid7(),
        'method' => 'GET',
        'endpoint' => 'api/_test/manual',
        'status' => RequestLogStatus::Iniciada,
    ]);

    expect(fn () => $log->markFinished(RequestLogStatus::Iniciada))
        ->toThrow(InvalidArgumentException::class);
});
