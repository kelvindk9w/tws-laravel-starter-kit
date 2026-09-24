<?php

declare(strict_types=1);

namespace App\Demo\Accounts;

use App\Core\Auth\Contracts\AccountProtection;
use App\Core\Auth\Models\User;
use App\Demo\Accounts\Exceptions\DemoAccountProtectedException;

/**
 * A demonstração implementa o ponto de extensão do produto "contas protegidas
 * contra alteração" (App\Core\Auth\Contracts\AccountProtection) com as CONTAS
 * DEMO — as regras e o porquê estão em DemoAccountGuard.
 *
 * Registrada pelo DemoServiceProvider. Sem a demo, o produto não protege
 * conta nenhuma.
 */
final class DemoAccountProtection implements AccountProtection
{
    /**
     * Conta demo pelo e-mail atual (config/ui.php), com o modo demo ligado ou
     * não: a interface do super admin mantém as contas demo fora das ações
     * mesmo quando as outras camadas estão desligadas.
     */
    public function reserves(User $user): bool
    {
        return in_array($user->email, array_filter([
            config('ui.demo_login.email'),
            config('ui.demo_admin.email'),
        ]), true);
    }

    public function protects(User $user): bool
    {
        return DemoAccountGuard::protects($user);
    }

    public function guardUpdate(User $user): void
    {
        if (! DemoAccountGuard::protects($user)) {
            return;
        }

        $proibidos = DemoAccountGuard::sensitiveChanges($user->getDirty());

        if ($proibidos !== []) {
            throw DemoAccountProtectedException::update($user->getOriginal('email'), $proibidos);
        }
    }

    public function guardDelete(User $user): void
    {
        if (DemoAccountGuard::protects($user)) {
            throw DemoAccountProtectedException::delete($user->getOriginal('email') ?? $user->email);
        }
    }
}
