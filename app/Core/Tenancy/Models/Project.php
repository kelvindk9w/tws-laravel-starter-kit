<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Models;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Identifiers\HasPublicCode;
use App\Core\Identifiers\RoutesByUuid;
use App\Core\Tenancy\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Projeto — camada ORGANIZACIONAL da conta (ADR-005).
 *
 * 1 login (pessoa) gerencia N projetos. Por ora o projeto nasce SÓ COM NOME
 * (pode existir sem empresa — ex.: pessoa antes de abrir CNPJ). No MVP é
 * metadado para separar dados e visões; a custódia segue uma por conta.
 *
 * Identificadores (3 camadas — ADR-010): `id` nunca exposto; `uuid` externo;
 * `codigo_publico` legível PRJ-xxxxxx.
 */
#[Fillable(['user_id', 'name', 'status'])]
class Project extends Model
{
    use HasPublicCode, HasUuids, RoutesByUuid;

    /**
     * Prefixo do código público legível (ADR-010): PRJ-xxxxxx.
     */
    protected const PUBLIC_CODE_PREFIX = 'PRJ';

    /**
     * Default da instância nova (espelha o default da migration) — sem isso,
     * o status ficaria null até o primeiro refresh após o INSERT.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Coluna preenchida automaticamente com UUID v7 na criação (HasUuids).
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    /**
     * Dono do projeto (tenant).
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Chaves de API vinculadas ao projeto (N:N — ADR-006).
     *
     * @return BelongsToMany<ApiKey, $this>
     */
    public function apiKeys(): BelongsToMany
    {
        return $this->belongsToMany(ApiKey::class, 'api_key_project')->withTimestamps();
    }
}
