<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Passo 2 da ação sensível (ADR-006): confirma o código de 6 dígitos
 * recebido por e-mail e emite o token de ação sensível.
 */
final class ConfirmSensitiveCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
