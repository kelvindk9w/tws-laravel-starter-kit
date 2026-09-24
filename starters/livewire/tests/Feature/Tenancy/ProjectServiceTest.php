<?php

declare(strict_types=1);

use App\Core\Tenancy\Enums\ProjectStatus;
use App\Core\Tenancy\Models\Project;
use App\Core\Tenancy\Services\ProjectService;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

// =============================================================================
// ProjectService — a regra ÚNICA do CRUD de projetos (painel e API v1).
//
// Posse em dois recortes: pela pessoa (painel) e pela chave (API, com o
// vínculo chave ↔ projeto e a marca de restrição). Fora do recorte é sempre a
// mesma exceção (404 uniforme). Os testes de ponta a ponta das telas e da API
// continuam em ProjectsTest (Panel e Tenancy) e ApiKeyProjectBindingTest; que
// a tela e a API não leem nem gravam Project por fora do serviço é trava do
// teste de arquitetura (BusinessLogicPlacementTest).
// =============================================================================

function projectService(): ProjectService
{
    return app(ProjectService::class);
}

it('lista só os projetos da pessoa, mais novos primeiro, com a contagem de chaves', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();

    $antigo = projectService()->create($dono, 'Antigo');
    $antigo->forceFill(['created_at' => now()->subDay()])->save();
    $novo = projectService()->create($dono, 'Novo');
    projectService()->create($outro, 'Alheio');

    criarChave($dono, ['project_uuids' => [$novo->uuid]]);

    $lista = projectService()->listForUser($dono);

    expect($lista->pluck('name')->all())->toBe(['Novo', 'Antigo'])
        ->and($lista->first()->api_keys_count)->toBe(1)
        ->and($lista->last()->api_keys_count)->toBe(0);
});

it('acha o projeto da pessoa pelo uuid e recusa o de outro dono com a mesma exceção de inexistente', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $meu = projectService()->create($dono, 'Meu');
    $alheio = projectService()->create($outro, 'Alheio');

    expect(projectService()->findForUser($dono, $meu->uuid)->is($meu))->toBeTrue();

    expect(fn () => projectService()->findForUser($dono, $alheio->uuid))->toThrow(ModelNotFoundException::class)
        ->and(fn () => projectService()->findForUser($dono, (string) str()->uuid()))->toThrow(ModelNotFoundException::class);
});

it('pela chave de conta enxerga todos os projetos do dono e nenhum de outro', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $a = projectService()->create($dono, 'A');
    $b = projectService()->create($dono, 'B');
    $alheio = projectService()->create($outro, 'Alheio');

    ['api_key' => $chave] = criarChave($dono);

    expect(projectService()->paginateForApiKey($chave, 15)->pluck('uuid')->sort()->values()->all())
        ->toBe(collect([$a->uuid, $b->uuid])->sort()->values()->all());

    expect(fn () => projectService()->findForApiKey($chave, $alheio->uuid))->toThrow(ModelNotFoundException::class);
});

it('pela chave restrita enxerga só os vinculados; fora do vínculo é a mesma exceção', function () {
    $dono = User::factory()->create();
    $a = projectService()->create($dono, 'A');
    $b = projectService()->create($dono, 'B');

    ['api_key' => $chave] = criarChave($dono, ['project_uuids' => [$a->uuid]]);

    expect(projectService()->paginateForApiKey($chave, 15)->pluck('uuid')->all())->toBe([$a->uuid])
        ->and(projectService()->findForApiKey($chave, $a->uuid)->is($a))->toBeTrue();

    expect(fn () => projectService()->findForApiKey($chave, $b->uuid))->toThrow(ModelNotFoundException::class);
});

it('respeita o tamanho de página pedido', function () {
    $dono = User::factory()->create();
    foreach (range(1, 3) as $i) {
        projectService()->create($dono, "P{$i}");
    }

    ['api_key' => $chave] = criarChave($dono);

    $pagina = projectService()->paginateForApiKey($chave, 2);

    expect($pagina->count())->toBe(2)
        ->and($pagina->total())->toBe(3);
});

it('cria o projeto só com nome, no dono informado, com código público PRJ-', function () {
    $dono = User::factory()->create();

    $projeto = projectService()->create($dono, 'Loja');

    expect($projeto->exists)->toBeTrue()
        ->and($projeto->user_id)->toBe($dono->id)
        ->and($projeto->name)->toBe('Loja')
        ->and($projeto->status)->toBe(ProjectStatus::Active)
        ->and($projeto->codigo_publico)->toStartWith('PRJ-');
});

it('atualiza só nome e status — dono e identificadores não mudam por aqui', function () {
    $dono = User::factory()->create();
    $outro = User::factory()->create();
    $projeto = projectService()->create($dono, 'Loja');
    $uuid = $projeto->uuid;

    projectService()->update($projeto, [
        'name' => 'Loja 2',
        'status' => ProjectStatus::Archived->value,
        'user_id' => $outro->id,
        'uuid' => (string) str()->uuid(),
    ]);

    $projeto->refresh();

    expect($projeto->name)->toBe('Loja 2')
        ->and($projeto->status)->toBe(ProjectStatus::Archived)
        ->and($projeto->user_id)->toBe($dono->id)
        ->and($projeto->uuid)->toBe($uuid);
});

it('excluir derruba o vínculo e deixa a chave restrita a nenhum projeto (fail-closed)', function () {
    $dono = User::factory()->create();
    $a = projectService()->create($dono, 'A');
    projectService()->create($dono, 'B');

    ['api_key' => $chave] = criarChave($dono, ['project_uuids' => [$a->uuid]]);

    projectService()->delete($a);

    expect(Project::query()->whereKey($a->getKey())->exists())->toBeFalse()
        ->and(DB::table('api_key_project')->where('project_id', $a->getKey())->count())->toBe(0)
        ->and($chave->fresh()?->isRestrictedToProjects())->toBeTrue()
        ->and(projectService()->paginateForApiKey($chave->fresh(), 15)->total())->toBe(0);
});
