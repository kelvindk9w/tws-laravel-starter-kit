<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Auth\Contracts\AccountProtection;
use App\Core\Auth\Models\User;

/**
 * Onde o produto pergunta se uma conta está protegida contra alteração.
 *
 * Consulta a implementação de AccountProtection registrada no container,
 * se houver. Sem nenhuma registrada, nenhuma conta é protegida: as perguntas
 * respondem "não" e as guardas deixam tudo passar. É isso que permite ao
 * produto funcionar sem a extensão que protege (hoje, a demonstração).
 */
final class ProtectedAccounts
{
    /**
     * A implementação registrada, ou null quando nenhuma extensão protege
     * contas nesta instalação.
     */
    public static function protection(): ?AccountProtection
    {
        return app()->bound(AccountProtection::class)
            ? app(AccountProtection::class)
            : null;
    }

    /**
     * Conta reservada pela extensão (proteção valendo ou não)?
     */
    public static function reserves(User $user): bool
    {
        return self::protection()?->reserves($user) ?? false;
    }

    /**
     * Proteção valendo agora para esta conta?
     */
    public static function protects(User $user): bool
    {
        return self::protection()?->protects($user) ?? false;
    }

    /**
     * Lança se a alteração em curso mexer em campo protegido.
     */
    public static function guardUpdate(User $user): void
    {
        self::protection()?->guardUpdate($user);
    }

    /**
     * Lança se a conta não puder ser excluída.
     */
    public static function guardDelete(User $user): void
    {
        self::protection()?->guardDelete($user);
    }
}
