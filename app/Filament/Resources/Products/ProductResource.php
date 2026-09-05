<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Core\Catalog\Models\Product;
use App\Core\Money\Money;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Produtos (super admin — vitrine demonstrativa do CRUD do kit).
 *
 * - Foto: upload validado pelo próprio campo (imagem real, máx. 2 MB) no
 *   disk public; o seeder grava URLs externas — a coluna resolve os dois
 *   (Product::imageUrl / pass-through de URL do ImageColumn).
 * - Valor: inteiro em centavos no banco (MoneyAsCents); o campo aceita o
 *   formato brasileiro e converte na borda via Money::parse — NUNCA float.
 * - Rotas e buscas usam o uuid (o id interno nunca é exposto — ADR-010).
 * - Paginação: 10/página; página e filtros refletidos na query string
 *   (#[Url] na página de listagem — ver ListProducts).
 */
final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function getNavigationLabel(): string
    {
        return __('admin.products.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.products.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_catalog');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label(__('admin.products.image'))
                    ->helperText(__('admin.products.image_hint'))
                    ->disk('public')
                    ->directory('products')
                    ->image()
                    ->maxSize(2048)
                    ->imagePreviewHeight('160')
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label(__('admin.products.title'))
                    ->required()
                    ->maxLength(160),
                TextInput::make('price')
                    ->label(__('admin.products.price'))
                    ->helperText(__('admin.products.price_hint'))
                    ->required()
                    // Exibe formatado (R$ 1.234,56) e converte de volta para
                    // centavos na gravação — dinheiro é sempre inteiro.
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '' : Money::format($state))
                    ->dehydrateStateUsing(fn (string $state): int => Money::parse($state))
                    // Regra do catálogo (decisão documentada): o valor precisa
                    // ser MAIOR QUE ZERO. Negativo não existe em catálogo
                    // (seria crédito, não produto) e zero também é recusado —
                    // item gratuito é uma decisão comercial explícita, não o
                    // resultado de um campo deixado em branco. Mínimo: 1 centavo.
                    ->rule(function (): \Closure {
                        return function (string $attribute, mixed $value, \Closure $fail): void {
                            try {
                                $cents = Money::parse((string) $value);
                            } catch (Throwable) {
                                $fail(__('admin.products.price_invalid'));

                                return;
                            }

                            if ($cents <= 0) {
                                $fail(__('admin.products.price_positive'));
                            }
                        };
                    }),
                Textarea::make('description')
                    ->label(__('admin.products.description'))
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label(__('admin.products.image'))
                    ->disk('public')
                    ->imageHeight(48)
                    ->defaultImageUrl('https://placehold.co/96x96?text=—'),
                TextColumn::make('title')
                    ->label(__('admin.products.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('price')
                    ->label(__('admin.products.price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('admin.products.description'))
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('admin.products.created_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // 10 por página, sem alternativa: a vitrine demonstra a
            // paginação refletida na URL (?page=2 — Livewire query string).
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10])
            ->filters([
                // SelectFilter (valores string): estado limpo na query string
                // (?filters[price_range]=ate_100 — ver ListProducts #[Url]).
                SelectFilter::make('price_range')
                    ->label(__('admin.products.filter_price'))
                    ->options([
                        'ate_100' => __('admin.products.price_up_to_100'),
                        '100_a_500' => __('admin.products.price_100_to_500'),
                        'acima_500' => __('admin.products.price_above_500'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'ate_100' => $query->where('price', '<', 10000),
                        '100_a_500' => $query->whereBetween('price', [10000, 50000]),
                        'acima_500' => $query->where('price', '>', 50000),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->successNotificationTitle(__('admin.products.deleted')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
