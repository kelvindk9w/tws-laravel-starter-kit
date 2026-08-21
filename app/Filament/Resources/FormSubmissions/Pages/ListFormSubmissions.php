<?php

declare(strict_types=1);

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

/**
 * Listagem de submissões com o filtro de origem refletido na URL
 * (?filters[origin][value]=classic) — #[Url] na propriedade da tabela;
 * a paginação (?page=N) é nativa do Livewire.
 */
final class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;
}
