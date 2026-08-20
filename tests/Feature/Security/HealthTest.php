<?php

declare(strict_types=1);

use App\Core\Logging\Models\RequestLog;

// GET /api/health — saúde da aplicação via BaseResource (ADR-010).
// Decisão: EXCLUÍDO do request log em banco para não poluir a trilha
// (health checks são barulhentos); segue no access log do nginx e passa
// por validação de segurança, headers e rate limit.

it('responde status, versão e correlation_id', function () {
    $response = $this->get('/api/health');

    $response->assertOk();
    $response->assertJsonPath('data.status', 'ok');
    $response->assertJsonStructure(['data' => ['status', 'version', 'correlation_id']]);

    expect($response->json('data.version'))->toBe(config('platform.version'))
        ->and($response->json('data.correlation_id'))->toBe($response->headers->get('X-Correlation-Id'));
});

it('não gera request log em banco (exclusão configurada)', function () {
    $this->get('/api/health')->assertOk();

    expect(RequestLog::query()->count())->toBe(0);
});

it('mesmo excluído do log, continua protegido pela validação de segurança', function () {
    $response = $this->get('/api/health?redirect=javascript:alert(1)');

    $response->assertUnprocessable();
});
