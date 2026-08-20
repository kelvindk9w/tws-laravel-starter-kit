<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Services;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\ApiKeys\Support\ApiKeyGenerator;
use App\Core\ApiKeys\Support\ApiKeyHasher;
use App\Core\Auth\Models\User;
use App\Core\Tenancy\Models\Project;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Casos de uso do motor de API keys (ADR-006): criação, rotação e revogação.
 *
 * Invariantes:
 * - A secreta em claro NUNCA toca o banco: o service a retorna uma única vez
 *   junto com a chave persistida (só o hash é gravado — checklist item 5).
 * - Scopes: padrão = tudo habilitado (config api_keys.default_scopes, ['*:*']);
 *   o usuário pode restringir por recurso:ação (menor privilégio).
 * - Rotação: nova chave herda nome, scopes e projetos da antiga; o dono escolhe
 *   a morte da antiga (imediata ou grace period em minutos).
 */
final class ApiKeyService
{
    public function __construct(
        private readonly ApiKeyGenerator $generator,
        private readonly ApiKeyHasher $hasher,
    ) {}

    /**
     * Cria uma chave de API para o usuário (dono/tenant).
     *
     * @param  array{name: string, scopes?: list<string>|null, expires_at?: string|null, project_uuids?: list<string>|null}  $data
     * @return array{api_key: ApiKey, secret_key: string} A secreta em claro —
     *                                                    exibir UMA vez e descartar. Não há recuperação.
     */
    public function create(User $user, array $data): array
    {
        $pair = $this->generator->generatePair();

        /** @var ApiKey $apiKey */
        $apiKey = DB::transaction(function () use ($user, $data, $pair): ApiKey {
            /** @var ApiKey $apiKey */
            $apiKey = ApiKey::createWithPublicCodeRetry([
                'user_id' => $user->id,
                'name' => $data['name'],
                'public_key' => $pair['public_key'],
                // SOMENTE o hash — nunca a sk_ em claro.
                'secret_hash' => $this->hasher->hash($pair['secret_key']),
                'scopes' => $data['scopes'] ?? config('api_keys.default_scopes', ['*:*']),
                'expires_at' => $data['expires_at'] ?? null,
                'status' => ApiKeyStatus::Active,
            ]);

            $projectIds = $this->resolveProjectIds($user, $data['project_uuids'] ?? null);

            if ($projectIds !== []) {
                $apiKey->projects()->sync($projectIds);
            }

            return $apiKey;
        });

        return ['api_key' => $apiKey, 'secret_key' => $pair['secret_key']];
    }

    /**
     * Rotaciona a chave: gera substituta herdando nome, scopes e projetos.
     *
     * @param  int|null  $gracePeriodMinutes  Morte da antiga: nulo/0 = imediata;
     *                                        positivo = janela de coexistência
     *                                        (escolha do usuário — ADR-006).
     * @return array{api_key: ApiKey, secret_key: string} Nova chave + secreta
     *                                                    em claro (exibida UMA vez).
     *
     * @throws InvalidArgumentException A chave não está ativa (não rotacionável).
     */
    public function rotate(ApiKey $current, ?int $gracePeriodMinutes): array
    {
        if ($current->status !== ApiKeyStatus::Active) {
            throw new InvalidArgumentException('Somente chaves ativas podem ser rotacionadas.');
        }

        $pair = $this->generator->generatePair();

        /** @var ApiKey $newKey */
        $newKey = DB::transaction(function () use ($current, $gracePeriodMinutes, $pair): ApiKey {
            /** @var ApiKey $newKey */
            $newKey = ApiKey::createWithPublicCodeRetry([
                'user_id' => $current->user_id,
                'name' => $current->name,
                'public_key' => $pair['public_key'],
                'secret_hash' => $this->hasher->hash($pair['secret_key']),
                'scopes' => $current->scopes,
                'expires_at' => $current->expires_at,
                'rotated_from_id' => $current->id,
                'status' => ApiKeyStatus::Active,
            ]);

            $newKey->projects()->sync($current->projects()->pluck('projects.id'));

            // Morte da antiga: imediata (sem grace) ou programada (grace_ends_at).
            $immediate = $gracePeriodMinutes === null || $gracePeriodMinutes <= 0;

            $current->forceFill([
                'rotated_to_id' => $newKey->id,
                'status' => $immediate ? ApiKeyStatus::Rotated : ApiKeyStatus::Active,
                'grace_ends_at' => $immediate ? now() : now()->addMinutes($gracePeriodMinutes),
            ])->save();

            return $newKey;
        });

        return ['api_key' => $newKey, 'secret_key' => $pair['secret_key']];
    }

    /**
     * Revoga a chave (irreversível). Idempotente: revogar de novo não falha.
     */
    public function revoke(ApiKey $apiKey): void
    {
        if ($apiKey->status === ApiKeyStatus::Revoked) {
            return;
        }

        $apiKey->forceFill(['status' => ApiKeyStatus::Revoked])->save();
    }

    /**
     * Resolve os UUIDs de projetos (N:N) garantindo que pertencem ao dono —
     * nunca vincula projeto de outro tenant (checklist itens 11/31).
     *
     * @param  list<string>|null  $projectUuids
     * @return list<int>
     *
     * @throws InvalidArgumentException Algum projeto não existe para o dono.
     */
    public function resolveProjectIds(User $user, ?array $projectUuids): array
    {
        if ($projectUuids === null || $projectUuids === []) {
            return [];
        }

        $projectUuids = array_values(array_unique($projectUuids));

        /** @var list<int> $ids */
        $ids = Project::query()
            ->where('user_id', $user->id)
            ->whereIn('uuid', $projectUuids)
            ->pluck('id')
            ->all();

        if (count($ids) !== count($projectUuids)) {
            throw new InvalidArgumentException('projects');
        }

        return $ids;
    }
}
