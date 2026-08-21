<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

/**
 * Listagem de produtos com estado refletido na URL (query string):
 * - paginação: ?page=N — nativo do Livewire (WithPagination);
 * - filtros: ?filters[...]=... — via #[Url] na propriedade da tabela.
 *
 * Assim a página/filtro de uma busca pode ser compartilhada por link.
 */
final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
