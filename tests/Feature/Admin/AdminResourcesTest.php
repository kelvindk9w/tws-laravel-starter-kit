<?php

declare(strict_types=1);

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Enums\UserStatus;
use App\Core\Auth\Models\User;
use App\Core\Logging\Enums\RequestLogStatus;
use App\Core\Logging\Models\RequestLog;
use App\Core\Tenancy\Models\Project;
use App\Core\Uploads\Models\Upload;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\RequestLogs\Pages\ListRequestLogs;
use App\Filament\Resources\Uploads\Pages\ListUploads;
use App\Filament\Resources\Users\Pages\ListUsers;
use Livewire\Livewire;

// =============================================================================
// Resources do super admin (Filament — Fase 6): listagens com conteúdo,
// ações administrativas (bloquear usuário, revogar chave) e consulta de
// auditoria de request logs com filtros.
// =============================================================================

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->admin);
});

it('lista usuários com código público, e-mail e status', function () {
    $user = User::factory()->create(['email' => 'cliente@example.com']);

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$user, $this->admin])
        ->assertSee('cliente@example.com')
        ->assertSee($user->codigo_publico);
});

it('bloqueia e desbloqueia usuário pela tabela', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('block', $user);

    expect($user->fresh()->status)->toBe(UserStatus::Blocked);

    Livewire::test(ListUsers::class)
        ->callTableAction('unblock', $user);

    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('lista chaves de API de TODOS os tenants (visão global) sem expor a secreta', function () {
    $dono = User::factory()->create(['email' => 'dono@example.com']);
    $key = app(ApiKeyService::class)->create($dono, ['name' => 'Chave Visível'])['api_key'];

    Livewire::test(ListApiKeys::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$key])
        ->assertSee('Chave Visível')
        ->assertSee('dono@example.com')
        ->assertDontSee('sk_'); // a secreta JAMAIS aparece (só hash no banco)
});

it('revoga chave de qualquer tenant pela ação administrativa', function () {
    $dono = User::factory()->create();
    $key = app(ApiKeyService::class)->create($dono, ['name' => 'Revogável'])['api_key'];

    Livewire::test(ListApiKeys::class)
        ->callTableAction('revoke', $key);

    expect($key->fresh()->status)->toBe(ApiKeyStatus::Revoked);
});

it('não oferece revogação para chave já revogada', function () {
    $dono = User::factory()->create();
    $key = app(ApiKeyService::class)->create($dono, ['name' => 'Morta'])['api_key'];
    app(ApiKeyService::class)->revoke($key);

    Livewire::test(ListApiKeys::class)
        ->assertTableActionHidden('revoke', $key);
});

it('lista projetos com dono e contagem de chaves vinculadas', function () {
    $dono = User::factory()->create();
    $projeto = Project::createWithPublicCodeRetry(['user_id' => $dono->id, 'name' => 'Projeto Admin']);

    Livewire::test(ListProjects::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$projeto])
        ->assertSee('Projeto Admin');
});

it('lista request logs e destaca órfãos (sem tenant = possível ataque)', function () {
    $logOrfao = RequestLog::query()->create([
        'correlation_id' => (string) str()->uuid7(),
        'tenant_uuid' => null,
        'method' => 'GET',
        'endpoint' => '/api/v1/inexistente',
        'status' => RequestLogStatus::Bloqueada,
    ]);

    $logNormal = RequestLog::query()->create([
        'correlation_id' => (string) str()->uuid7(),
        'tenant_uuid' => (string) str()->uuid7(),
        'method' => 'POST',
        'endpoint' => '/api/v1/projects',
        'status' => RequestLogStatus::Concluida,
    ]);

    Livewire::test(ListRequestLogs::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$logOrfao, $logNormal])
        ->assertSee(__('admin.request_logs.orphan'))
        ->assertSee('/api/v1/projects')
        // Filtro de status: só o BLOQUEADA permanece.
        ->filterTable('status', RequestLogStatus::Bloqueada->value)
        ->assertCanSeeTableRecords([$logOrfao])
        ->assertCanNotSeeTableRecords([$logNormal]);
});

it('filtra request logs: somente órfãos', function () {
    $orfao = RequestLog::query()->create([
        'correlation_id' => (string) str()->uuid7(),
        'tenant_uuid' => null,
        'method' => 'GET',
        'endpoint' => '/api/v1/x',
        'status' => RequestLogStatus::Iniciada,
    ]);

    $normal = RequestLog::query()->create([
        'correlation_id' => (string) str()->uuid7(),
        'tenant_uuid' => (string) str()->uuid7(),
        'method' => 'GET',
        'endpoint' => '/api/v1/y',
        'status' => RequestLogStatus::Concluida,
    ]);

    Livewire::test(ListRequestLogs::class)
        ->filterTable('orphans', true)
        ->assertCanSeeTableRecords([$orfao])
        ->assertCanNotSeeTableRecords([$normal]);
});

it('lista uploads com dono, tipo e tamanho', function () {
    $dono = User::factory()->create();
    $upload = Upload::query()->create([
        'user_id' => $dono->id,
        'disk' => 'local',
        'path' => 'avatars/abc.png',
        'original_name' => 'documento.png',
        'mime' => 'image/png',
        'size' => 2048,
        'sha256' => hash('sha256', 'x'),
    ]);

    Livewire::test(ListUploads::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$upload])
        ->assertSee('documento.png')
        ->assertSee('image/png');
});
