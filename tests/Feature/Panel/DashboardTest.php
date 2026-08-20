<?php

declare(strict_types=1);

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use App\Livewire\Dashboard;
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
        ->assertSee(platform()->primaryColor)
        ->assertSee(__('panel.nav.api_keys'));
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
