<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
                Password::min((int) config('auth.password_rules.min_length', 12))
                    ->letters()
                    ->mixedCase()
                    ->numbers(),
            ],
        ];
    }
}
