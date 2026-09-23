<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Status da conta do usuário. Deny-by-default: somente contas Active
 * operam (checklist item 13) — no login, a cada requisição web
 * (EnsureAccountIsActive), na API (ResolveTenant) e no /admin
 * (User::canAccessPanel). Pending tem o MESMO tratamento que Blocked:
 * conta ainda não liberada não opera em lugar nenhum.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Pending = 'pending';
}
