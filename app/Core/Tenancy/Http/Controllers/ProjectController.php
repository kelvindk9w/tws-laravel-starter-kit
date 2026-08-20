<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Http\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Tenancy\Http\Requests\StoreProjectRequest;
use App\Core\Tenancy\Http\Requests\UpdateProjectRequest;
use App\Core\Tenancy\Http\Resources\ProjectResource;
use App\Core\Tenancy\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD de projetos da API v1 (ADR-005 — camada organizacional).
 *
 * Isolamento de tenant: TODA consulta filtra pelo dono autenticado; projeto
 * de outro tenant = 404 uniforme (checklist itens 11/31).
 */
final class ProjectController extends Controller
{
    /**
     * GET /api/v1/projects — lista os projetos do tenant (scope projects:read).
     */
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->where('user_id', $this->tenantUser()->id)
            ->latest()
            ->paginate((int) config('api_keys.pagination.per_page', 15));

        return ProjectResource::collection($projects);
    }

    /**
     * POST /api/v1/projects — cria projeto só com nome (scope projects:create).
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validated();

        /** @var Project $project */
        $project = Project::createWithPublicCodeRetry([
            'user_id' => $this->tenantUser()->id,
            'name' => $data['name'],
        ]);

        return ProjectResource::make($project)
            ->additional(['message' => __('api_keys.projects.created')])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/projects/{uuid} — detalhe (scope projects:read).
     */
    public function show(string $uuid): JsonResponse
    {
        return response()->json([
            'data' => ProjectResource::make($this->findOwned($uuid)),
        ]);
    }

    /**
     * PUT /api/v1/projects/{uuid} — atualiza nome/status (scope projects:update).
     */
    public function update(UpdateProjectRequest $request, string $uuid): JsonResponse
    {
        $project = $this->findOwned($uuid);

        /** @var array{name?: string, status?: string} $data */
        $data = $request->validated();

        $project->update($data);

        return response()->json([
            'message' => __('api_keys.projects.updated'),
            'data' => ProjectResource::make($project->refresh()),
        ]);
    }

    /**
     * DELETE /api/v1/projects/{uuid} — remove (scope projects:delete).
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->findOwned($uuid)->delete();

        return response()->json(['message' => __('api_keys.projects.deleted')]);
    }

    /**
     * Localiza o projeto do tenant pelo UUID — 404 uniforme para projeto de
     * outro tenant ou inexistente (anti-IDOR/BOLA, checklist item 11).
     */
    private function findOwned(string $uuid): Project
    {
        /** @var Project */
        return Project::query()
            ->where('uuid', $uuid)
            ->where('user_id', $this->tenantUser()->id)
            ->firstOrFail();
    }

    private function tenantUser(): User
    {
        /** @var User */
        return tenant() ?? throw new \LogicException('Rota sem resolve.tenant.');
    }
}
