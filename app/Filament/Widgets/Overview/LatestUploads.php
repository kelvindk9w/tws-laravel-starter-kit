<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Overview;

use App\Core\Uploads\Models\Upload;
use App\Filament\Resources\Uploads\UploadResource;
use App\Filament\Widgets\Support\BaseLatestRecordsWidget;
use App\Filament\Widgets\Support\MetricFormat;
use App\Filament\Widgets\Support\Period;
use BackedEnum;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * Últimos arquivos aceitos pela função global de upload (Fase 5): nome
 * sanitizado, tipo REAL (magic bytes, nunca o declarado) e tamanho.
 */
final class LatestUploads extends BaseLatestRecordsWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 6];

    /**
     * @return Builder<Upload>
     */
    protected function latestQuery(Period $period): Builder
    {
        return Upload::query();
    }

    protected function latestHeading(): string
    {
        return __('admin.dashboards.overview.latest_uploads');
    }

    protected function latestIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedCloudArrowUp;
    }

    protected function latestUrl(): ?string
    {
        return UploadResource::getUrl();
    }

    /**
     * @return array<TextColumn>
     */
    protected function latestColumns(): array
    {
        return [
            TextColumn::make('original_name')
                ->label(__('admin.dashboards.overview.upload_name'))
                ->weight(FontWeight::SemiBold)
                ->limit(28),

            TextColumn::make('mime')
                ->label(__('admin.dashboards.overview.upload_type'))
                ->badge()
                ->color('gray'),

            TextColumn::make('size')
                ->label(__('admin.dashboards.overview.upload_size'))
                ->formatStateUsing(fn ($state): string => MetricFormat::Bytes->display((float) $state))
                ->alignEnd(),

            $this->whenColumn(),
        ];
    }
}
