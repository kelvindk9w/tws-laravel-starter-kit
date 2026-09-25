<?php

declare(strict_types=1);

namespace App\Demo\Filament\Resources\Products\Pages;

use App\Demo\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Twstec\Kit\Admin\Support\BaseListRecords;

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
