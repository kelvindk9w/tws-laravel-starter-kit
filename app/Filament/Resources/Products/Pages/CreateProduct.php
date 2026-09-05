<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Teto de largura do formulário (crítica de design): campos de título e
     * valor esticados a toda a tela dificultam a leitura e não dizem nada
     * sobre o tamanho esperado da entrada.
     */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::FourExtraLarge;
    }
}
