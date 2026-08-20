<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\TransactionPasswordRequest;
use App\Core\Auth\Models\User;
use App\Core\Auth\Services\TransactionPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Definição/alteração da senha de TRANSAÇÃO (ADR-006).
 *
 * Regras:
 * - Hash SEPARADO da senha de login (cast 'hashed' → Argon2id).
 * - Deve ser DIFERENTE da senha de login.
 * - Na alteração, a senha de transação atual é exigida.
 */
final class TransactionPasswordController
{
    public function __construct(
        private readonly TransactionPasswordService $transactionPasswords,
    ) {}

    public function edit(): View
    {
        return view('auth.transaction-password');
    }

    /**
     * @throws ValidationException
     */
    public function update(TransactionPasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{transaction_password: string, current_transaction_password?: string} $validated */
        $validated = $request->validated();

        // Lógica única no TransactionPasswordService (compartilhada com o
        // painel Livewire — Fase 6).
        $this->transactionPasswords->update(
            $user,
            $validated['transaction_password'],
            $validated['current_transaction_password'] ?? null,
        );

        return back()->with('status', __('auth.transaction_password.saved'));
    }
}
