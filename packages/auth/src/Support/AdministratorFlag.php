<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Support;

use Illuminate\Database\Eloquent\Model;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Contracts\IdentifiesAdministrators;

/**
 * Critério padrão de "administrador" quando nenhum pacote registrou o dele:
 * a coluna `is_admin` da conta (a mesma que o kit usa para a entrada no
 * /admin). Sem a coluna, ninguém é administrador.
 */
final class AdministratorFlag implements IdentifiesAdministrators
{
    public function isAdministrator(AuthUser $user): bool
    {
        return $user instanceof Model && (bool) $user->getAttribute('is_admin');
    }
}
