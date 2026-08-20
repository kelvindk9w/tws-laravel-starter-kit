<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Http\Resources;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * Serialização da chave de API (ADR-010 — nunca expor campos internos).
 *
 * NUNCA inclui secret_hash nem qualquer forma da secreta. A sk_ em claro só
 * sai na resposta de criação/rotação, fora deste Resource (campo avulso
 * `secret_key` no envelope — exibição única, ADR-006).
 *
 * @mixin ApiKey
 */
final class ApiKeyResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->publicIdentifiers($this->resource),
            'name' => $this->name,
            'public_key' => $this->public_key,
            'scopes' => $this->scopes,
            'status' => $this->status->value,
            'expires_at' => $this->isoTimestamp($this->expires_at),
            'last_used_at' => $this->isoTimestamp($this->last_used_at),
            'grace_ends_at' => $this->isoTimestamp($this->grace_ends_at),
            'projects' => $this->whenLoaded('projects', fn (): array => $this->projects
                ->map(fn ($project): array => [
                    'uuid' => $project->uuid,
                    'codigo_publico' => $project->codigo_publico,
                    'name' => $project->name,
                ])
                ->all()),
            'created_at' => $this->isoTimestamp($this->created_at),
        ];
    }
}
