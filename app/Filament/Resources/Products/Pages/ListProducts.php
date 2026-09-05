<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\BaseListRecords;
use Filament\Actions\CreateAction;

/**
 * Listagem de produtos com estado refletido na URL (query string):
 * - paginação: ?page=N — nativo do Livewire (WithPagination);
 * - filtros: ?filters[...]=... — via #[Url] na base (BaseListRecords).
 *
 * Assim a página/filtro de uma busca pode ser compartilhada por link.
 */
final class ListProducts extends BaseListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getResourceHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
