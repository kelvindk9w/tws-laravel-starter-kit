<?php

declare(strict_types=1);

namespace App\Core\Auth\Services;

use App\Core\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Definição/alteração da senha de TRANSAÇÃO (ADR-006) — lógica única,
 * consumida pelo controller web (Fase 3) e pelo painel Livewire (Fase 6).
 *
 * Regras:
 * - Hash SEPARADO da senha de login (cast 'hashed' → Argon2id).
 * - Deve ser DIFERENTE da senha de login.
 * - Na alteração, a senha de transação atual é exigida.
 */
final class TransactionPasswordService
{
    /**
     * @throws ValidationException Senha atual incorreta ou nova igual à de login.
     */
    public function update(User $user, string $newPassword, ?string $currentPassword = null): void
    {
        if ($user->hasTransactionPassword()
            && ! Hash::check((string) $currentPassword, (string) $user->transaction_password)) {
            throw ValidationException::withMessages([
                'current_transaction_password' => __('auth.transaction_password.current_invalid'),
            ]);
        }

        // A senha de transação NUNCA pode ser igual à senha de login.
        if (Hash::check($newPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.transaction_password.same_as_login'),
            ]);
        }

        $user->transaction_password = $newPassword;
        $user->transaction_password_set_at = now();
        $user->save();
    }
}
