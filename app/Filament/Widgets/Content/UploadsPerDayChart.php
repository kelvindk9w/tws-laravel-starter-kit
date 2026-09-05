<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Content;

use App\Core\Showcase\Models\FormSubmission;
use App\Core\Uploads\Models\Upload;
use App\Filament\Widgets\Support\BaseTimeSeriesWidget;
use App\Filament\Widgets\Support\ChartSeries;
use App\Filament\Widgets\Support\Metric;
use App\Filament\Widgets\Support\Period;
use App\Filament\Widgets\Support\StatusPalette;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Volume operacional por dia — arquivos que entraram e mensagens que
 * chegaram, lado a lado em barras. É o gráfico que mostra o "ritmo" do
 * trabalho da semana, e o que denuncia um pico anômalo de entrada.
 *
 * Para nascer com conteúdo em instalação nova, os dois históricos são
 * semeados: UploadSeeder e FormSubmissionHistorySeeder (30 dias espalhados).
 */
final class UploadsPerDayChart extends BaseTimeSeriesWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 8];

    protected string $chartType = 'bar';

    public function getHeading(): string
    {
        return __('admin.dashboards.content.chart_intake_heading');
    }

    protected function emptyIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedCloudArrowUp;
    }

    /**
     * @return list<ChartSeries>
     */
    protected function chartSeries(Period $period): array
    {
        return [
            ChartSeries::make(
                __('admin.dashboards.content.chart_intake_uploads'),
                Metric::count(fn () => Upload::query(), $period)->series(),
                StatusPalette::Neutral,
            ),
            ChartSeries::make(
                __('admin.dashboards.content.chart_intake_submissions'),
                Metric::count(fn () => FormSubmission::query(), $period)->series(),
                StatusPalette::Success,
            ),
        ];
    }
}
