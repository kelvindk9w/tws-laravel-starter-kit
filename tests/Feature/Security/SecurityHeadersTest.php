<?php

declare(strict_types=1);

// Headers de segurança OWASP (checklist item 20) — presentes em TODAS as
// respostas, inclusive bloqueios e erros (middleware mais externo da cadeia).

it('respostas normais carregam os headers de segurança', function () {
    $response = $this->get('/api/health');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'");
});

it('resposta de bloqueio (422) também carrega os headers', function () {
    Route::post('/api/_test/echo', fn () => response()->json(['ok' => true]));

    $response = $this->postJson('/api/_test/echo', ['x' => '<script>alert(1)</script>']);

    $response->assertUnprocessable();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
});

it('resposta de erro 404 também carrega os headers', function () {
    $response = $this->get('/api/nao-existe');

    $response->assertNotFound();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('não envia HSTS fora de HTTPS/produção', function () {
    $response = $this->get('/api/health');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('CORS não libera origem nenhuma por padrão (restritivo)', function () {
    $response = $this->get('/api/health', ['Origin' => 'https://site-malicioso.example']);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});
