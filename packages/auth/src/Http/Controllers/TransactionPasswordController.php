<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Http\Requests\TransactionPasswordRequest;
use Twstec\Kit\Auth\Services\TransactionPasswordService;

/**
 * Definição/alteração da senha de TRANSAÇÃO (separada da de login).
 *
 * Regras:
 * - Hash SEPARADO da senha de login (cast 'hashed' → Argon2id).
 * - Deve ser DIFERENTE da senha de login.
 * - Na alteração, a senha de transação atual é exigida.
 *
 * A tela é do front (no starter Livewire, a view `auth.transaction-password`).
 * O `throttle:sensitive` vem com o controller (HasMiddleware).
 */
final class TransactionPasswordController implements HasMiddleware
{
    public function __construct(
        private readonly TransactionPasswordService $transactionPasswords,
    ) {}

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('throttle:sensitive', only: ['update'])];
    }

    /**
     * @throws ValidationException
     */
    public function update(TransactionPasswordRequest $request): RedirectResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        /** @var array{transaction_password: string, current_transaction_password?: string} $validated */
        $validated = $request->validated();

        // Lógica única no TransactionPasswordService (compartilhada com o
        // painel Livewire).
        $this->transactionPasswords->update(
            $user,
            $validated['transaction_password'],
            $validated['current_transaction_password'] ?? null,
        );

        return back()->with('status', __('auth.transaction_password.saved'));
    }
}
