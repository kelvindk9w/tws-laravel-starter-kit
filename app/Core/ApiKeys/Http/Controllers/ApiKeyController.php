<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Http\Controllers;

use App\Core\ApiKeys\Http\Requests\RotateApiKeyRequest;
use App\Core\ApiKeys\Http\Requests\StoreApiKeyRequest;
use App\Core\ApiKeys\Http\Requests\SyncApiKeyProjectsRequest;
use App\Core\ApiKeys\Http\Resources\ApiKeyResource;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Services\ApiKeyService;
use App\Core\Auth\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * API v1 do motor de chaves (ADR-006). Grupo protegido por resolve.tenant
 * (+ scope por rota); criação e rotação exigem ainda o token de ação
 * sensível (senha de transação + 2FA por e-mail — Fase 3).
 *
 * Isolamento de tenant (checklist itens 11/31): TODA consulta é filtrada
 * pelo dono autenticado; chave de outro tenant = 404 uniforme (nunca 403,
 * para não revelar existência).
 */
final class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $apiKeys) {}

    /**
     * GET /api/v1/api-keys — lista as chaves do tenant (scope api-keys:read).
     */
    public function index(): AnonymousResourceCollection
    {
        $keys = ApiKey::query()
            ->where('user_id', $this->tenantUser()->id)
            ->with('projects')
            ->latest()
            ->paginate((int) config('api_keys.pagination.per_page', 15));

        return ApiKeyResource::collection($keys);
    }

    /**
     * POST /api/v1/api-keys — cria chave (scope api-keys:create + ação
     * sensível). A secreta em claro sai UMA única vez, no campo avulso
     * `secret_key` do envelope — no banco fica somente o hash (ADR-006).
     */
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        /** @var array{name: string, scopes?: list<string>|null, expires_at?: string|null, project_uuids?: list<string>|null} $data */
        $data = $request->validated();

        try {
            $result = $this->apiKeys->create($this->tenantUser(), $data);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'project_uuids' => __('api_keys.projects.invalid'),
            ]);
        }

        return ApiKeyResource::make($result['api_key']->load('projects'))
            ->additional([
                'message' => __('api_keys.keys.created'),
                // Exibição ÚNICA — não há recuperação (perdeu = rotaciona).
                'secret_key' => $result['secret_key'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/api-keys/{uuid} — revoga a chave (scope api-keys:revoke).
     */
    public function destroy(string $uuid): JsonResponse
    {
        $apiKey = $this->findOwned($uuid);

        $this->apiKeys->revoke($apiKey);

        return response()->json([
            'message' => __('api_keys.keys.revoked'),
            'data' => ApiKeyResource::make($apiKey->refresh()),
        ]);
    }

    /**
     * POST /api/v1/api-keys/{uuid}/rotate — rotaciona (scope api-keys:rotate
     * + ação sensível). Corpo: grace_period_minutes (nulo/0 = morte imediata
     * da antiga; positivo = janela de coexistência — escolha do usuário).
     */
    public function rotate(RotateApiKeyRequest $request, string $uuid): JsonResponse
    {
        $apiKey = $this->findOwned($uuid);

        /** @var array{grace_period_minutes?: int|null} $data */
        $data = $request->validated();

        try {
            $result = $this->apiKeys->rotate($apiKey, $data['grace_period_minutes'] ?? null);
        } catch (\InvalidArgumentException) {
            abort(422, __('api_keys.keys.not_rotatable'));
        }

        return ApiKeyResource::make($result['api_key']->load('projects'))
            ->additional([
                'message' => __('api_keys.keys.rotated'),
                // Nova secreta — exibição ÚNICA.
                'secret_key' => $result['secret_key'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/v1/api-keys/{uuid}/projects — vínculo N:N chave ↔ projetos
     * (scope api-keys:assign). Lista vazia = sem vínculo (conta toda — ADR-005).
     */
    public function syncProjects(SyncApiKeyProjectsRequest $request, string $uuid): JsonResponse
    {
        $apiKey = $this->findOwned($uuid);

        /** @var array{project_uuids: list<string>} $data */
        $data = $request->validated();

        try {
            $projectIds = $this->apiKeys->resolveProjectIds($this->tenantUser(), $data['project_uuids']);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'project_uuids' => __('api_keys.projects.invalid'),
            ]);
        }

        $apiKey->projects()->sync($projectIds);

        return response()->json([
            'message' => __('api_keys.keys.projects_synced'),
            'data' => ApiKeyResource::make($apiKey->refresh()->load('projects')),
        ]);
    }

    /**
     * Localiza a chave do tenant pelo UUID — 404 uniforme para chave de
     * outro tenant ou inexistente (anti-IDOR/BOLA, checklist item 11).
     */
    private function findOwned(string $uuid): ApiKey
    {
        /** @var ApiKey */
        return ApiKey::query()
            ->where('uuid', $uuid)
            ->where('user_id', $this->tenantUser()->id)
            ->firstOrFail();
    }

    /**
     * Tenant autenticado (o ResolveTenant garante; o user resolver da
     * request aponta para o dono da chave).
     */
    private function tenantUser(): User
    {
        /** @var User */
        return tenant() ?? throw new \LogicException('Rota sem resolve.tenant.');
    }
}
