<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Support\BaseListRecords;

/**
 * Listagem de submissões. Da base vêm o alternador tabela/cards e o filtro
 * de origem refletido na URL (?filters[origin][value]=classic); a paginação
 * (?page=N) é nativa do Livewire.
 */
final class ListFormSubmissions extends BaseListRecords
{
    protected static string $resource = FormSubmissionResource::class;
}
