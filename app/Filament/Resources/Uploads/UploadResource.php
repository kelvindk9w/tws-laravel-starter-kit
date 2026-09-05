<?php

declare(strict_types=1);

namespace App\Filament\Resources\Uploads;

use App\Core\Uploads\Models\Upload;
use App\Filament\Resources\Uploads\Pages\ListUploads;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Uploads — visão global (super admin, Fase 6). Somente leitura: todo
 * registro aqui passou pela validação de segurança da Fase 5 (rejeitados
 * não tocam o banco). Abrir arquivo = URL assinada de curta duração.
 */
final class UploadResource extends Resource
{
    protected static ?string $model = Upload::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    // Navegação do /admin: TODO resource tem ícone (crítica de design #6 —
    // metade da nav aparecia como bolinha sem ícone).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    public static function getNavigationLabel(): string
    {
        return __('admin.uploads.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.uploads.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.uploads.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.group_security');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo_publico')
                    ->label(__('admin.users.code'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('original_name')
                    ->label(__('admin.uploads.original_name'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('mime')
                    ->label(__('admin.uploads.mime'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('size')
                    ->label(__('admin.uploads.size'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1, ',', '.').' KB')
                    ->sortable(),
                TextColumn::make('owner.email')
                    ->label(__('admin.uploads.owner'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('tenant_uuid')
                    ->label(__('admin.uploads.tenant'))
                    ->placeholder('—')
                    ->limit(12),
                TextColumn::make('created_at')
                    ->label(__('panel.common.created_at'))
                    ->dateTime('d/m/Y H:i', platform()->displayTimezone)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.uploads.open'))
                    ->iconButton()
                    ->url(fn (Upload $record): string => $record->url())
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUploads::route('/'),
        ];
    }
}
