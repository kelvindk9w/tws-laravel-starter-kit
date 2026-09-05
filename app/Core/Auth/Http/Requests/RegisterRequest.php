<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use App\Core\Auth\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro de usuário (checklist item 9 — validação server-side de TODA
 * entrada via Form Request). Força mínima da senha via config (ADR-007).
 */
final class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                PasswordPolicy::rule(),
            ],
        ];
    }
}
