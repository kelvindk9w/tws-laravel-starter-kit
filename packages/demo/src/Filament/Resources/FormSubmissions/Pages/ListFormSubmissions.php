<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Filament\Resources\FormSubmissions\Pages;

use Twstec\Kit\Admin\Support\BaseListRecords;
use Twstec\Kit\Demo\Filament\Resources\FormSubmissions\FormSubmissionResource;

/**
 * Listagem de submissões. Da base vêm o alternador tabela/cards e o filtro
 * de origem refletido na URL (?filters[origin][value]=classic); a paginação
 * (?page=N) é nativa do Livewire.
 */
final class ListFormSubmissions extends BaseListRecords
{
    protected static string $resource = FormSubmissionResource::class;
}
