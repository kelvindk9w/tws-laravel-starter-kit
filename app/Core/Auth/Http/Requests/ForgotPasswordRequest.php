<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solicitação de link de redefinição de senha (checklist item 9).
 * A resposta é SEMPRE a mesma, existindo ou não o e-mail (anti-enumeração —
 * checklist item 11).
 */
final class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email'],
        ];
    }
}
