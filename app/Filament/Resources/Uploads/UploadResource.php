<?php

declare(strict_types=1);

namespace App\Filament\Resources\Uploads;

use App\Core\Uploads\Models\Upload;
use App\Filament\Resources\Uploads\Pages\ListUploads;
use App\Filament\Support\AdminColumns;
use App\Filament\Support\BaseResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Uploads — visão global (super admin, Fase 6). Somente leitura: todo
 * registro aqui passou pela validação de segurança da Fase 5 (rejeitados
 * não tocam o banco). Abrir arquivo = URL assinada de curta duração.
 */
final class UploadResource extends BaseResource
{
    protected static ?string $model = Upload::class;

    protected static string $translationKey = 'admin.uploads';

    protected static ?string $navigationGroupKey = 'admin.nav.group_security';

    // Navegação do /admin: TODO resource tem ícone (crítica de design #6 —
    // metade da nav aparecia como bolinha sem ícone).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function tableColumns(): array
    {
        return [
            AdminColumns::publicCode(),
            TextColumn::make('original_name')
                ->label(__('admin.uploads.original_name'))
                ->searchable()
                ->limit(40),
            self::mimeColumn(),
            self::sizeColumn(),
            TextColumn::make('owner.email')
                ->label(__('admin.uploads.owner'))
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('tenant_uuid')
                ->label(__('admin.uploads.tenant'))
                ->placeholder('—')
                ->limit(12),
            AdminColumns::dateTime('created_at', __('panel.common.created_at')),
        ];
    }

    public static function cardComponents(): array
    {
        return [
            Stack::make([
                Split::make([
                    TextColumn::make('original_name')
                        ->label(__('admin.uploads.original_name'))
                        ->weight(FontWeight::SemiBold)
                        ->searchable()
                        ->limit(40),
                    self::mimeColumn()->grow(false),
                ]),
                Split::make([
                    self::sizeColumn()
                        ->size(TextSize::Small)
                        ->color('gray'),
                    AdminColumns::dateTime('created_at', __('panel.common.created_at'))
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->grow(false),
                ]),
                TextColumn::make('owner.email')
                    ->label(__('admin.uploads.owner'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->color('gray')
                    ->size(TextSize::Small)
                    ->placeholder('—')
                    ->searchable(),
            ])->space(2),
        ];
    }

    private static function mimeColumn(): TextColumn
    {
        return TextColumn::make('mime')
            ->label(__('admin.uploads.mime'))
            ->badge()
            ->color('gray');
    }

    private static function sizeColumn(): TextColumn
    {
        return TextColumn::make('size')
            ->label(__('admin.uploads.size'))
            ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1, ',', '.').' KB')
            ->sortable();
    }

    protected static function tableExtras(Table $table): Table
    {
        return $table
            ->recordActions([
                Action::make('open')
                    ->label(__('admin.uploads.open'))
                    ->iconButton()
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
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
