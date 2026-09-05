<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Content;

use App\Core\Catalog\Models\Product;
use App\Core\Money\Money;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\Support\BaseLatestRecordsWidget;
use App\Filament\Widgets\Support\Period;
use BackedEnum;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Últimos produtos cadastrados, com miniatura e preço.
 *
 * O preço vem em CENTAVOS do banco (ADR-004: dinheiro é sempre inteiro) e é
 * formatado só aqui, na borda — pelo mesmo helper de moeda do resto do kit.
 */
final class LatestProducts extends BaseLatestRecordsWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 6];

    /**
     * @return Builder<Product>
     */
    protected function latestQuery(Period $period): Builder
    {
        return Product::query();
    }

    protected function latestHeading(): string
    {
        return __('admin.dashboards.content.latest_products');
    }

    protected function latestIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedShoppingBag;
    }

    protected function latestUrl(): ?string
    {
        return ProductResource::getUrl();
    }

    /**
     * @return array<ImageColumn|TextColumn>
     */
    protected function latestColumns(): array
    {
        return [
            ImageColumn::make('image')
                ->label('')
                ->disk('public')
                ->imageHeight(36)
                ->defaultImageUrl('https://placehold.co/72x72?text=—'),

            TextColumn::make('title')
                ->label(__('admin.dashboards.content.product_title'))
                ->weight(FontWeight::SemiBold)
                ->limit(34),

            TextColumn::make('price')
                ->label(__('admin.dashboards.content.product_price'))
                ->formatStateUsing(fn (int $state): string => Money::format($state))
                ->alignEnd(),

            $this->whenColumn(),
        ];
    }
}
