<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Passo 1 da ação sensível: solicita o código de verificação
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
