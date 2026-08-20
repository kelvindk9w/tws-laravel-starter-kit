<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Passo 1 da ação sensível (ADR-006): solicita o código de verificação
 * apresentando a senha de transação.
 */
final class RequestSensitiveCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_password' => ['required', 'string'],
        ];
    }
}
