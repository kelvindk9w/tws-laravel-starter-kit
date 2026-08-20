<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Status da conta do usuário. Login é deny-by-default: somente contas
 * Active autenticam (checklist item 13).
 */
enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Pending = 'pending';
}
