<?php

declare(strict_types=1);

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use App\Livewire\Projects\Index;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

// =============================================================================
// Projetos pela UI (Livewire — Fase 6, ADR-005): CRUD só com nome, tudo na
// mesma tela (criar/editar/excluir inline). Consome o model/invariantes da
// Fase 4 — nada duplicado.
// =============================================================================

it('exige autenticação (deny-by-default)', function () {
    $this->get('/projects')->assertRedirect(route('login'));
});

it('lista apenas os projetos do próprio usuário', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();

    Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Meu Projeto']);
    Project::createWithPublicCodeRetry(['user_id' => $outro->id, 'name' => 'Projeto Alheio']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertOk()
        ->assertSee('Meu Projeto')
        ->assertDontSee('Projeto Alheio');
});

it('cria projeto pela UI com código público gerado (PRJ-)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Loja Virtual')
        ->call('create')
        ->assertHasNoErrors();

    $project = Project::query()->sole();

    expect($project->name)->toBe('Loja Virtual')
        ->and($project->user_id)->toBe($user->id)
        ->and($project->codigo_publico)->toStartWith('PRJ-')
        ->and($project->uuid)->not->toBeEmpty();
});

it('valida o nome obrigatório na criação', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name']);

    expect(Project::query()->count())->toBe(0);
});

it('edita o nome inline', function () {
    $user = User::factory()->create();
    $project = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Nome Antigo']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startEdit', $project->uuid)
        ->set('editingName', 'Nome Novo')
        ->call('update')
        ->assertHasNoErrors();

    expect($project->fresh()->name)->toBe('Nome Novo');
});

it('exclui com confirmação inline', function () {
    $user = User::factory()->create();
    $project = Project::createWithPublicCodeRetry(['user_id' => $user->id, 'name' => 'Vai Sair']);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startDelete', $project->uuid)
        ->assertSet('confirmingDeleteUuid', $project->uuid)
        ->call('removeProject');

    expect(Project::query()->count())->toBe(0);
});

it('não toca em projeto de outro tenant (404 uniforme — anti-IDOR)', function () {
    $user = User::factory()->create();
    $outro = User::factory()->create();
    $alheio = Project::createWithPublicCodeRetry(['user_id' => $outro->id, 'name' => 'Alheio']);

    // findOwned() usa firstOrFail → ModelNotFoundException (404 na request
    // real do Livewire; no harness de teste a exceção propaga).
    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('startDelete', $alheio->uuid);
})->throws(ModelNotFoundException::class);
