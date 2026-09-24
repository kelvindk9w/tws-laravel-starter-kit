<?php

declare(strict_types=1);

namespace App\Core\Tenancy\Enums;

/**
 * Status do projeto (camada organizacional).
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
