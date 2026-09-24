<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Twstec\Kit\Auth\PasswordPolicy;

/**
 * Redefinição de senha com token recebido por e-mail (validação server-side via Form Request).
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
                PasswordPolicy::rule(),
            ],
        ];
    }
}
