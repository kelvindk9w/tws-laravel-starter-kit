<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Definição/alteração da senha de TRANSAÇÃO (ADR-006 — separada da senha
 * de login). Na redefinição, a senha de transação ATUAL é exigida.
 */
final class TransactionPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_transaction_password' => [
                $this->user()->hasTransactionPassword() ? 'required' : 'nullable',
                'string',
            ],
            'transaction_password' => [
                'required',
                'confirmed',
                Password::min((int) config('auth.transaction_password.min_length', 8))
                    ->letters()
                    ->numbers(),
            ],
        ];
    }
}
