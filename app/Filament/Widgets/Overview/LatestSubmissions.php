<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Overview;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Showcase\Support\SubmissionExcerpt;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Widgets\Support\BaseLatestRecordsWidget;
use App\Filament\Widgets\Support\Period;
use BackedEnum;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

/**
 * O que chegou pelos formulários, mais recente primeiro.
 *
 * O trecho exibido passa pelo MESMO neutralizador da listagem do recurso
 * (SubmissionExcerpt): um dashboard não é lugar para exibir payload de
 * tentativa de ataque por extenso, nem escapado.
 */
final class LatestSubmissions extends BaseLatestRecordsWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 6];

    /**
     * @return Builder<FormSubmission>
     */
    protected function latestQuery(Period $period): Builder
    {
        return FormSubmission::query();
    }

    protected function latestHeading(): string
    {
        return __('admin.dashboards.overview.latest_submissions');
    }

    protected function latestIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedInboxArrowDown;
    }

    protected function latestUrl(): ?string
    {
        return FormSubmissionResource::getUrl();
    }

    /**
     * @return array<TextColumn>
     */
    protected function latestColumns(): array
    {
        return [
            TextColumn::make('nickname')
                ->label(__('admin.dashboards.overview.submission_from'))
                ->weight(FontWeight::SemiBold)
                ->formatStateUsing(fn (?string $state): string => SubmissionExcerpt::neutralize($state, 32)),

            TextColumn::make('attack_type')
                ->label(__('admin.dashboards.overview.submission_state'))
                ->badge()
                ->formatStateUsing(fn (FormSubmission $record): string => $record->isBlocked()
                    ? FormSubmissionResource::attackLabel($record->attack_type)
                    : __('admin.dashboards.common.received'))
                ->color(fn (FormSubmission $record): string => $record->isBlocked() ? 'danger' : 'gray')
                ->default('-'),

            $this->whenColumn(),
        ];
    }
}
