<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditEvents\Pages;

use App\Filament\Resources\AuditEvents\AuditEventResource;
use App\Filament\Support\BaseListRecords;

/**
 * Listagem da trilha de auditoria. Filtros refletidos na URL (da base) — é
 * assim que o detalhe de uma requisição aponta para as ações dela
 * (?filters[correlation_id][value]=...).
 */
final class ListAuditEvents extends BaseListRecords
{
    protected static string $resource = AuditEventResource::class;
}
