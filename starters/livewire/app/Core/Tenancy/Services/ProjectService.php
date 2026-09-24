<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Services;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Regra ÚNICA do CRUD de projetos — a tela do painel (Livewire) e a API v1
 * chamam este serviço; nenhuma das duas lê ou grava Project direto.
 *
 * Posse (isolamento por dono) mora aqui, em dois recortes:
 *
 * - Pela PESSOA logada (painel): só os projetos dela.
 * - Pela CHAVE DE API (API v1): Project::visibleToApiKey() — os do dono da
 *   chave e, se a chave é restrita (`restricted_to_projects`), só os
 *   vinculados a ela, inclusive nenhum (fail-closed).
 *
 * Projeto fora do recorte, de outro dono ou inexistente é a MESMA exceção
 * (ModelNotFoundException → 404 uniforme): não revela que o recurso existe.
 * Atualizar e excluir recebem o projeto já achado por um dos recortes.
 */
final class ProjectService
{
    /**
     * Atributos que a atualização aceita — o resto é ignorado (o dono e os
     * identificadores nunca mudam por aqui).
     */
    private const UPDATABLE = ['name', 'status'];

    /**
     * Projetos da pessoa, mais novos primeiro, com a contagem de chaves
     * vinculadas (a lista do painel).
     *
     * @return Collection<int, Project>
     */
    public function listForUser(User $user): Collection
    {
        return $this->ownedBy($user)
            ->withCount('apiKeys')
            ->latest()
            ->get();
    }

    /**
     * Projetos que a chave enxerga, mais novos primeiro, paginados (a lista
     * da API).
     *
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForApiKey(ApiKey $apiKey, int $perPage): LengthAwarePaginator
    {
        return $this->visibleTo($apiKey)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Projeto da pessoa pelo UUID.
     *
     * @throws ModelNotFoundException<Project> De outro dono ou inexistente.
     */
    public function findForUser(User $user, string $uuid): Project
    {
        /** @var Project */
        return $this->ownedBy($user)
            ->byUuid($uuid)
            ->firstOrFail();
    }

    /**
     * Projeto que a chave enxerga pelo UUID.
     *
     * @throws ModelNotFoundException<Project> De outro dono, fora do vínculo da chave ou inexistente.
     */
    public function findForApiKey(ApiKey $apiKey, string $uuid): Project
    {
        /** @var Project */
        return $this->visibleTo($apiKey)
            ->byUuid($uuid)
            ->firstOrFail();
    }

    /**
     * Cria o projeto só com nome, com o código público PRJ-xxxxxx gerado
     * (com nova tentativa em caso de colisão).
     *
     * Quem pode criar é decidido antes: no painel, a pessoa logada; na API,
     * só chave de conta (middleware account.key).
     */
    public function create(User $owner, string $name): Project
    {
        /** @var Project */
        return Project::createWithPublicCodeRetry([
            'user_id' => $owner->id,
            'name' => $name,
        ]);
    }

    /**
     * Atualiza nome e/ou status de um projeto já achado por um dos recortes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): Project
    {
        $project->update(Arr::only($attributes, self::UPDATABLE));

        return $project;
    }

    /**
     * Exclui um projeto já achado por um dos recortes.
     *
     * O vínculo N:N com as chaves cai junto (cascadeOnDelete na pivot). A
     * chave que só atendia este projeto CONTINUA restrita — agora a nenhum
     * projeto (fail-closed); voltar à conta toda é ação explícita de quem
     * gerencia as chaves.
     */
    public function delete(Project $project): void
    {
        $project->delete();
    }

    /**
     * @return Builder<Project>
     */
    private function ownedBy(User $user): Builder
    {
        return Project::query()->where('user_id', $user->id);
    }

    /**
     * @return Builder<Project>
     */
    private function visibleTo(ApiKey $apiKey): Builder
    {
        return Project::query()->visibleToApiKey($apiKey);
    }
}
