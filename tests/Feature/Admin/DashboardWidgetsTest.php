<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Exceptions\AppendOnlyViolationException;
use App\Core\Logging\Models\RequestLog;
use App\Filament\Widgets\PlatformStatsOverview;
use App\Filament\Widgets\RequestsChart;
use Database\Seeders\RequestLogSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

// =============================================================================
// Crítica de design #3 — o /admin abria com o AccountWidget de fábrica
// sozinho. Agora abre com números reais + gráfico de 30 dias.
// =============================================================================

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('o dashboard do admin renderiza os widgets com dados, sem o AccountWidget', function () {
    $this->seed(RequestLogSeeder::class);

    // Os widgets do Filament são componentes Livewire carregados sob demanda:
    // a página registra os componentes; o CONTEÚDO é verificado nos testes
    // de widget abaixo.
    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('PlatformStatsOverview')
        ->and($html)->toContain('RequestsChart')
        // AccountWidget de fábrica ("Bem-vindo(a)") saiu do painel.
        ->and($html)->not->toContain('AccountWidget');

    expect(filament()->getWidgets())->toBe([
        PlatformStatsOverview::class,
        RequestsChart::class,
    ]);
});

it('os números do StatsOverview vêm das tabelas reais', function () {
    User::factory()->count(4)->create();

    RequestLog::query()->create([
        'correlation_id' => (string) Str::uuid7(),
        'method' => 'GET',
        'endpoint' => 'api/v1/projects',
        'status' => RequestLogStatus::Concluida,
        'http_status_response' => 500,
    ]);

    $widget = Livewire::test(PlatformStatsOverview::class)->assertOk();

    $widget->assertSee(__('admin.dashboard.users_total'))
        ->assertSee(__('admin.dashboard.errors_24h'))
        // 5 usuários = 4 criados + o admin do beforeEach.
        ->assertSee('5');
});

it('o gráfico devolve 30 dias e conta as requisições do dia', function () {
    RequestLog::query()->create([
        'correlation_id' => (string) Str::uuid7(),
        'method' => 'GET',
        'endpoint' => 'dashboard',
        'status' => RequestLogStatus::Concluida,
        'http_status_response' => 200,
    ]);

    $dados = (fn (): array => $this->getCachedData())->call(Livewire::test(RequestsChart::class)->assertOk()->instance());

    expect($dados['labels'])->toHaveCount(30)
        ->and($dados['datasets'])->toHaveCount(2)
        ->and($dados['datasets'][0]['data'])->toHaveCount(30)
        // A requisição de hoje é o último ponto da série.
        ->and(end($dados['datasets'][0]['data']))->toBeGreaterThanOrEqual(1);
});

it('o seeder de request logs alimenta o gráfico e é idempotente', function () {
    $this->seed(RequestLogSeeder::class);
    $primeiro = RequestLog::query()->count();

    expect($primeiro)->toBeGreaterThan(300);

    $this->seed(RequestLogSeeder::class);

    expect(RequestLog::query()->count())->toBe($primeiro);

    $dados = (fn (): array => $this->getCachedData())->call(Livewire::test(RequestsChart::class)->assertOk()->instance());

    // Todo dia da janela tem tráfego — o gráfico nunca sai achatado.
    expect(array_sum($dados['datasets'][0]['data']))->toBeGreaterThan(200)
        ->and(min($dados['datasets'][0]['data']))->toBeGreaterThan(0);
});

it('o seeder de request logs respeita o append-only do model', function () {
    $this->seed(RequestLogSeeder::class);

    $log = RequestLog::query()->first();

    expect(fn () => $log->update(['endpoint' => 'hackeado']))
        ->toThrow(AppendOnlyViolationException::class);
});
