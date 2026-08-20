<?php

declare(strict_types=1);

namespace App\Core\Auth\Http\Controllers;

use App\Core\Auth\Http\Requests\TransactionPasswordRequest;
use App\Core\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
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

        if ($user->hasTransactionPassword()
            && ! Hash::check((string) ($validated['current_transaction_password'] ?? ''), (string) $user->transaction_password)) {
            throw ValidationException::withMessages([
                'current_transaction_password' => __('auth.transaction_password.current_invalid'),
            ]);
        }

        // A senha de transação NUNCA pode ser igual à senha de login.
        if (Hash::check($validated['transaction_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.transaction_password.same_as_login'),
            ]);
        }

        $user->transaction_password = $validated['transaction_password'];
        $user->transaction_password_set_at = now();
        $user->save();

        return back()->with('status', __('auth.transaction_password.saved'));
    }
}
