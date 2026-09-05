<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Models\User;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Support\Platform;
use App\Core\Tenancy\Models\Project;
use App\Livewire\Dashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

// =============================================================================
// Dashboard do painel do usuário (Livewire — Fase 6, ADR-005/011).
// Testes validam CONTEÚDO (ADR-010), não só status HTTP.
// =============================================================================

it('exige autenticação (deny-by-default)', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('renderiza com saudação e código público do usuário', function () {
    $user = User::factory()->create(['name' => 'Maria da Silva']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('panel.dashboard.greeting', ['name' => 'Maria da Silva']))
        ->assertSee($user->codigo_publico);
});

it('página completa carrega o branding via platform() no layout (ADR-007)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(platform()->name)
        ->assertSee(__('panel.nav.api_keys'));
});

it('sem PLATFORM_PRIMARY_COLOR o layout NÃO injeta --brand (primária neutra dos tokens)', function () {
    config(['platform.primary_color' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('--brand:', false);
});

it('com PLATFORM_PRIMARY_COLOR definido o layout injeta o override --brand', function () {
    config(['platform.primary_color' => '#0e7490']);
    app()->forgetInstance(Platform::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('--brand: #0e7490', false);
});

it('mostra os contadores de chaves ativas e projetos do próprio usuário', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();

    app(ApiKeyService::class)->create($user, ['name' => 'Minha chave']);
    app(ApiKeyService::class)->create($outro, ['name' => 'Chave alheia']);
    Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Loja A']);

    // Isolamento: o dashboard NUNCA conta dados de outro tenant.
    expect(ApiKey::query()->count())->toBe(2);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('activeKeysCount', 1)
        ->assertViewHas('projectsCount', 1);
});

// =============================================================================
// Tráfego real da conta (request_logs — ADR-004/010). O dashboard mostra o que
// o kit JÁ COLETA: métricas, série diária e as últimas chamadas da API.
// Os logs são criados aqui por INSERT direto (o model é append-only e nasce
// pelo middleware, não por factory).
// =============================================================================

/**
 * Grava um log de requisição do tenant informado.
 */
function gravarRequestLog(string $tenantUuid, CarbonImmutable|Carbon $quando, string $endpoint = '/api/v1/projects', int $httpStatus = 200): void
{
    DB::table('request_logs')->insert([
        'uuid' => (string) Str::uuid7(),
        'correlation_id' => (string) Str::uuid7(),
        'tenant_uuid' => $tenantUuid,
        'ip' => '127.0.0.1',
        'method' => 'GET',
        'endpoint' => $endpoint,
        'status' => RequestLogStatus::Concluida->value,
        'http_status_response' => $httpStatus,
        'created_at' => $quando,
    ]);
}

it('conta as requisições dos últimos 7 dias apenas do próprio tenant', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();

    gravarRequestLog($user->uuid, now()->subDay());
    gravarRequestLog($user->uuid, now()->subDays(3));
    gravarRequestLog($user->uuid, now()->subDays(20));   // fora da janela de 7 dias
    gravarRequestLog($outro->uuid, now()->subDay());     // outro tenant

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('recentRequestsCount', 2);
});

it('monta a série diária completa de 30 dias, com zero nos dias sem tráfego', function () {
    $user = User::factory()->create();

    gravarRequestLog($user->uuid, now());
    gravarRequestLog($user->uuid, now());

    $chart = Livewire::actingAs($user)->test(Dashboard::class)->viewData('chart');

    // 30 rótulos = 30 dias; nenhum buraco na série (senão o gráfico ligaria
    // dois dias distantes como se o intervalo não existisse).
    expect($chart['labels'])->toHaveCount(30)
        ->and($chart['values'])->toHaveCount(30)
        ->and(array_sum($chart['values']))->toBe(2)
        ->and(end($chart['values']))->toBe(2);
});

it('lista as 5 últimas chamadas da API com endpoint e status', function () {
    $user = User::factory()->create();

    foreach (range(1, 7) as $i) {
        gravarRequestLog($user->uuid, now()->subMinutes($i), '/api/v1/uploads/'.$i, 201);
    }

    $component = Livewire::actingAs($user)->test(Dashboard::class);

    expect($component->viewData('recentCalls'))->toHaveCount(5);

    // Conteúdo, não só contagem (ADR-010): a chamada mais recente aparece na
    // tela; a sexta mais antiga, não.
    $component->assertSee('/api/v1/uploads/1')
        ->assertSee('201')
        ->assertDontSee('/api/v1/uploads/7');
});

it('mostra o estado vazio do gráfico e da lista quando não há tráfego', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee(__('panel.dashboard.chart_empty_title'))
        ->assertSee(__('panel.dashboard.recent_calls_empty_title'))
        ->assertSee(__('panel.common.never'));
});
