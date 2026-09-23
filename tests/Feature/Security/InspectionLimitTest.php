<?php

declare(strict_types=1);

use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Security\AttackDetector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

// Teto de bytes inspecionados pelo AttackDetector (Lote 2): a detecção roda
// antes da autenticação, então sem teto qualquer anônimo comprava regex sobre
// o corpo inteiro a cada requisição. O excedente é RECUSADO (413) e gravado
// como BLOQUEADA — nunca aceito sem inspeção.

beforeEach(function () {
    Route::post('/api/_test/echo', fn () => response()->json(['ok' => true]));
    config()->set('security.validation.max_inspected_bytes', 200);
});

it('aceita corpo dentro do teto', function () {
    $this->postJson('/api/_test/echo', ['texto' => str_repeat('a', 150)])->assertOk();
});

it('recusa com 413 o corpo acima do teto e grava BLOQUEADA sem copiar o conteúdo', function () {
    $response = $this->postJson('/api/_test/echo', ['texto' => str_repeat('conteudo-grande ', 40)]);

    $response->assertStatus(413)->assertHeader('X-Correlation-Id');

    $log = RequestLog::query()->sole();

    expect($log->status)->toBe(RequestLogStatus::Bloqueada)
        ->and($log->attack_type)->toBe('payload_too_large')
        ->and($log->http_status_response)->toBe(413)
        ->and($log->correlation_id)->toBe($response->headers->get('X-Correlation-Id'))
        ->and($log->payload['limite_bytes'])->toBe(200)
        ->and(json_encode($log->payload))->not->toContain('conteudo-grande');
});

it('o ataque escondido DEPOIS do teto não passa: a requisição inteira é recusada', function () {
    $this->postJson('/api/_test/echo', [
        'enchimento' => str_repeat('x', 500),
        'comment' => '<script>alert(1)</script>',
    ])->assertStatus(413);

    // Nada chegou à rota: nenhuma linha CONCLUIDA 200.
    expect(RequestLog::query()->where('http_status_response', 200)->exists())->toBeFalse();
});

it('conta a query string e as chaves, não só os valores do corpo', function () {
    $this->getJson('/api/health?'.str_repeat('k', 300).'=1')->assertStatus(413);
});

it('conteúdo de arquivo enviado não conta (só os metadados são inspecionados)', function () {
    Route::post('/api/_test/upload', fn () => response()->json(['ok' => true]));

    $this->post('/api/_test/upload', [
        'arquivo' => UploadedFile::fake()->create('grande.pdf', 2048, 'application/pdf'),
    ])->assertOk();
});

it('o detector para de contar assim que passa do teto', function () {
    $detector = new AttackDetector;

    expect($detector->exceedsInspectionBudget(['a' => str_repeat('x', 10)], 11))->toBeFalse()
        ->and($detector->exceedsInspectionBudget(['a' => str_repeat('x', 10)], 10))->toBeTrue()
        ->and($detector->exceedsInspectionBudget(['nivel' => ['fundo' => [str_repeat('x', 50)]]], 40))->toBeTrue()
        ->and($detector->exceedsInspectionBudget(['n' => 123456789, 'b' => true], 3))->toBeFalse();
});
