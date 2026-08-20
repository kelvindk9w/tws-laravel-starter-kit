<?php

declare(strict_types=1);

namespace App\Core\ApiKeys\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Vínculo N:N chave ↔ projetos (ADR-005/006). Sempre combinado com
 * resolve.tenant + scope:api-keys:assign (rota).
 *
 * `project_uuids` presente e vazio = remove TODOS os vínculos (a chave volta
 * a enxergar a conta toda — semântica do ADR-005).
 */
final class SyncApiKeyProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorização é dos middlewares (tenant + scope).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_uuids' => ['present', 'array'],
            'project_uuids.*' => ['uuid'],
        ];
    }
}
