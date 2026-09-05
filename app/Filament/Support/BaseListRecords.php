<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Base das páginas de LISTAGEM do super admin.
 *
 * Entrega de graça, para todo resource que estende BaseResource:
 *
 * - o ALTERNADOR tabela/cards no cabeçalho (só aparece quando o resource
 *   declara `cardComponents()`). O clique grava a preferência (ViewMode —
 *   sessão, por usuário e por recurso) e reconstrói a tabela na hora com
 *   `resetTable()`: a Table é montada no boot do Livewire, então trocar a
 *   sessão sem remontar deixaria a tela um clique atrasada;
 * - filtros refletidos na query string (?filters[...]=...), para que uma
 *   busca do operador possa ser compartilhada por link.
 *
 * Ações próprias da página (ex.: "Novo usuário") vão em
 * `getResourceHeaderActions()` — assim o alternador nunca some porque
 * alguém sobrescreveu `getHeaderActions()`.
 */
abstract class BaseListRecords extends ListRecords
{
    /** @var array<string, mixed>|null */
    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /**
     * Ações específicas da página (create, importar etc.).
     *
     * @return array<Action>
     */
    protected function getResourceHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...$this->viewModeHeaderActions(),
            ...$this->getResourceHeaderActions(),
        ];
    }

    /**
     * O botão do alternador. Rótulo e ícone são CLOSURES: são avaliados na
     * renderização, então o botão já volta descrevendo o próximo destino
     * ("Ver em tabela") no mesmo clique que trocou o modo.
     *
     * @return array<Action>
     */
    protected function viewModeHeaderActions(): array
    {
        $resource = static::getResource();

        if (! is_subclass_of($resource, BaseResource::class) || ! $resource::hasCardView()) {
            return [];
        }

        return [
            Action::make('toggleViewMode')
                ->label(fn (): string => ViewMode::for($resource)->isGrid()
                    ? __('admin.common.view_as_table')
                    : __('admin.common.view_as_cards'))
                ->icon(fn () => ViewMode::for($resource)->isGrid()
                    ? Heroicon::OutlinedTableCells
                    : Heroicon::OutlinedSquares2x2)
                ->color('gray')
                ->outlined()
                ->action(function () use ($resource): void {
                    ViewMode::store($resource, ViewMode::for($resource)->toggled());

                    $this->resetTable();
                }),
        ];
    }
}
