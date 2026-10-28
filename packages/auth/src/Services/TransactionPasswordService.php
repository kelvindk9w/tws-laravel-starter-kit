<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Services;

use Illuminate\Validation\ValidationException;
use Twstec\Kit\Auth\Contracts\AuthUser;

/**
 * Definição/alteração da senha de TRANSAÇÃO (separada da de login) — lógica única,
 * consumida pelo controller web e pelo painel Livewire (sem regra duplicada).
 *
 * Regras:
 * - Hash SEPARADO da senha de login (cast 'hashed' → Argon2id).
 * - Deve ser DIFERENTE da senha de login.
 * - Na alteração, a senha de transação atual é exigida.
 * - Tentativas limitadas por conta (PasswordConfirmation), em qualquer front:
 *   a senha de transação atual errada conta no limite da senha de transação
 *   (o mesmo do pedido de código da ação sensível); e a comparação da senha
 *   nova com a de login conta no limite da senha de LOGIN — a recusa "igual à
 *   de login" diria a quem está com a sessão de outra pessoa que o palpite
 *   era a senha de login, e sem limite isso viraria um adivinhador.
 */
final class TransactionPasswordService
{
    public function __construct(
        private readonly PasswordConfirmation $passwords,
    ) {}

    /**
     * @throws ValidationException Senha atual incorreta, nova igual à de login
     *                             ou tentativas esgotadas.
     */
    public function update(AuthUser $user, string $newPassword, ?string $currentPassword = null): void
    {
        if ($user->hasTransactionPassword()
            && ! $this->passwords->confirmTransactionPassword($user, (string) $currentPassword, 'current_transaction_password')) {
            throw ValidationException::withMessages([
                'current_transaction_password' => __('auth.transaction_password.current_invalid'),
            ]);
        }

        // A senha de transação NUNCA pode ser igual à senha de login.
        if ($this->passwords->matchesAndCount($user, PasswordConfirmation::LOGIN_PASSWORD, 'transaction_password', $newPassword, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'transaction_password' => __('auth.transaction_password.same_as_login'),
            ]);
        }

        $user->transaction_password = $newPassword;
        $user->transaction_password_set_at = now();
        $user->save();
    }
}
