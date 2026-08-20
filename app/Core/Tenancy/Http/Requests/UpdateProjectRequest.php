<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Http\Requests;

use App\Core\Tenancy\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Atualização de projeto (ADR-005): nome e/ou status (active/archived).
 */
final class UpdateProjectRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
        ];
    }
}
