<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Redefinição de senha com token recebido por e-mail (checklist item 9).
 */
final class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email'],
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
