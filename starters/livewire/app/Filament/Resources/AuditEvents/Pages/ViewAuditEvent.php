<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditEvents\Pages;

use App\Filament\Resources\AuditEvents\AuditEventResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Detalhe de um evento: quem, de onde, em qual registro e o resumo do que
 * mudou — já mascarado na gravação (AuditChanges), nunca decifrado aqui.
 */
final class ViewAuditEvent extends ViewRecord
{
    protected static string $resource = AuditEventResource::class;
}
