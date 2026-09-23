<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Middleware\ResolveTenant;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

// =============================================================================
// LIMITE DA API POR CHAVE, NÃO POR IP (App\Core\Security\ApiRateLimit).
//
// Antes, o `throttle:api` rodava antes do `resolve.tenant` e contava sempre
// por IP: duas integrações atrás do mesmo NAT dividiam o orçamento, e a mesma
// chave ganhava orçamento novo a cada IP. Agora:
//
//   - requisição autenticada conta pela CHAVE (ou pelo tenant, se configurado);
//   - falha de autenticação conta por IP, num balde próprio que o próprio
//     ResolveTenant consulta — um laço de chaves inválidas não escapa;
//   - todo 429 sai no envelope de erro padrão da API, com Retry-After.
// =============================================================================

const ROTA_API = '/api/v1/projects';

it('duas chaves diferentes do MESMO IP têm orçamentos independentes', function () {
    config()->set('security.rate_limit.api', 2);

    ['api_key' => $chaveA, 'secret_key' => $segredoA] = criarChave(User::factory()->create());
    ['api_key' => $chaveB, 'secret_key' => $segredoB] = criarChave(User::factory()->create());

    $this->getJson(ROTA_API, headersApi($chaveA, $segredoA))->assertOk();
    $this->getJson(ROTA_API, headersApi($chaveA, $segredoA))->assertOk();
    $this->getJson(ROTA_API, headersApi($chaveA, $segredoA))->assertTooManyRequests();

    // A chave B, do mesmo IP, não foi tocada pelo estouro da A.
    $this->getJson(ROTA_API, headersApi($chaveB, $segredoB))->assertOk();
    $this->getJson(ROTA_API, headersApi($chaveB, $segredoB))->assertOk();
});

it('o limite acompanha a CHAVE: trocar de IP não dá orçamento novo', function () {
    config()->set('security.rate_limit.api', 2);

    ['api_key' => $chave, 'secret_key' => $segredo] = criarChave(User::factory()->create());

    $this->getJson(ROTA_API, headersApi($chave, $segredo))->assertOk();
    $this->getJson(ROTA_API, headersApi($chave, $segredo))->assertOk();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->getJson(ROTA_API, headersApi($chave, $segredo))
        ->assertTooManyRequests();
});

it('o 429 por chave sai no envelope de erro padrão da API, traduzido e com Retry-After', function () {
    config()->set('security.rate_limit.api', 1);

    ['api_key' => $chave, 'secret_key' => $segredo] = criarChave(User::factory()->create());

    $this->getJson(ROTA_API, headersApi($chave, $segredo))->assertOk();

    $response = $this->getJson(ROTA_API, headersApi($chave, $segredo));

    assertErroApi($response, 429, 'too_many_requests')
        ->assertJsonPath('error.message', __('api.errors.too_many_requests'))
        ->assertHeader('Retry-After');
});

it('com RATE_LIMIT_API_BY=tenant as chaves do mesmo dono somam', function () {
    config()->set('security.rate_limit.api', 2);
    config()->set('security.rate_limit.api_by', 'tenant');

    $dono = User::factory()->create();
    ['api_key' => $chaveA, 'secret_key' => $segredoA] = criarChave($dono);
    ['api_key' => $chaveB, 'secret_key' => $segredoB] = criarChave($dono);

    $this->getJson(ROTA_API, headersApi($chaveA, $segredoA))->assertOk();
    $this->getJson(ROTA_API, headersApi($chaveB, $segredoB))->assertOk();
    $this->getJson(ROTA_API, headersApi($chaveA, $segredoA))->assertTooManyRequests();

    // Outro dono segue com orçamento próprio.
    ['api_key' => $outra, 'secret_key' => $outroSegredo] = criarChave(User::factory()->create());
    $this->getJson(ROTA_API, headersApi($outra, $outroSegredo))->assertOk();
});

it('chave inválida em laço recebe 429 (não escapa do limite por rodar antes do throttle)', function () {
    config()->set('security.rate_limit.api_auth_failures', 3);
    // Limite por chave folgado: prova que o 429 vem do balde de FALHAS.
    config()->set('security.rate_limit.api', 1000);

    $invalida = ['X-Api-Key' => 'pk_test_inexistente', 'Authorization' => 'Bearer sk_test_errada'];

    for ($i = 0; $i < 3; $i++) {
        $this->getJson(ROTA_API, $invalida)->assertUnauthorized();
    }

    $response = $this->getJson(ROTA_API, $invalida);

    assertErroApi($response, 429, 'too_many_requests')
        ->assertJsonPath('error.message', __('api.errors.too_many_requests'))
        ->assertHeader('Retry-After');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});

it('sem credencial nenhuma também conta como falha', function () {
    config()->set('security.rate_limit.api_auth_failures', 2);

    $this->getJson(ROTA_API)->assertUnauthorized();
    $this->getJson(ROTA_API)->assertUnauthorized();
    $this->getJson(ROTA_API)->assertTooManyRequests();
});

it('IP bloqueado por falhas é recusado até com chave válida; outro IP segue normal', function () {
    config()->set('security.rate_limit.api_auth_failures', 2);

    ['api_key' => $chave, 'secret_key' => $segredo] = criarChave(User::factory()->create());

    $this->getJson(ROTA_API, ['X-Api-Key' => 'pk_test_x', 'Authorization' => 'Bearer sk_test_y'])->assertUnauthorized();
    $this->getJson(ROTA_API, ['X-Api-Key' => 'pk_test_x', 'Authorization' => 'Bearer sk_test_y'])->assertUnauthorized();

    // Intercalar uma chave boa não zera o balde.
    $this->getJson(ROTA_API, headersApi($chave, $segredo))->assertTooManyRequests();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->getJson(ROTA_API, headersApi($chave, $segredo))
        ->assertOk();
});

it('requisições autenticadas com sucesso não gastam o balde de falhas', function () {
    config()->set('security.rate_limit.api_auth_failures', 2);

    ['api_key' => $chave, 'secret_key' => $segredo] = criarChave(User::factory()->create());

    for ($i = 0; $i < 5; $i++) {
        $this->getJson(ROTA_API, headersApi($chave, $segredo))->assertOk();
    }
});

it('em toda rota com resolve.tenant, o throttle roda DEPOIS dele', function () {
    $rotas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $rota): bool => in_array('resolve.tenant', $rota->gatherMiddleware(), true));

    expect($rotas)->not->toBeEmpty();

    foreach ($rotas as $rota) {
        $ordem = array_values(array_map(
            fn (string $middleware): string => explode(':', $middleware, 2)[0],
            array_filter(app('router')->gatherRouteMiddleware($rota), 'is_string'),
        ));

        $tenant = array_search(ResolveTenant::class, $ordem, true);
        $throttle = array_search(ThrottleRequests::class, $ordem, true);

        expect($tenant)->not->toBeFalse()
            ->and($throttle)->not->toBeFalse()
            ->and($tenant)->toBeLessThan($throttle, "Rota {$rota->uri()}: throttle antes do resolve.tenant");
    }
});
