<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Http\Resources;

use App\Core\Tenancy\Models\Project;
use Illuminate\Http\Request;
use Twstec\Kit\Foundation\Http\Resources\BaseResource;

/**
 * Serialização do projeto (nunca expor o `id` interno).
 *
 * @mixin Project
 */
final class ProjectResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->publicIdentifiers($this->resource),
            'name' => $this->name,
            'status' => $this->status->value,
            'created_at' => $this->isoTimestamp($this->created_at),
        ];
    }
}
