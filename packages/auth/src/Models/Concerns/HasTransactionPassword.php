<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Senha de TRANSAÇÃO (`transaction_password`): separada da senha de login,
 * com hash próprio (Argon2id, via config/hashing.php). Autoriza as ações
 * sensíveis (ver Services\SensitiveActionService).
 *
 * Colunas com o cast desta trait (para a análise estática — Larastan/PHPStan
 * — do model que a usa: o cast entra em tempo de execução e o analisador não
 * o vê):
 *
 * @property string|null $transaction_password
 * @property Carbon|null $transaction_password_set_at
 */
trait HasTransactionPassword
{
    public function initializeHasTransactionPassword(): void
    {
        $this->mergeCasts([
            'transaction_password' => 'hashed',
            'transaction_password_set_at' => 'datetime',
        ]);
    }

    /**
     * O usuário já definiu a senha de transação?
     */
    public function hasTransactionPassword(): bool
    {
        return $this->transaction_password !== null;
    }
}
