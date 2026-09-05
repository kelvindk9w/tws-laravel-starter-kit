<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Growth;

use App\Core\Auth\Models\User;
use App\Filament\Widgets\Support\BaseTimeSeriesWidget;
use App\Filament\Widgets\Support\ChartSeries;
use App\Filament\Widgets\Support\Metric;
use App\Filament\Widgets\Support\Period;
use App\Filament\Widgets\Support\StatusPalette;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Crescimento ACUMULADO da base: a curva parte de quantos usuários já
 * existiam antes da janela e sobe com os cadastros de cada dia. É a leitura
 * de "estamos crescendo?" que um gráfico de barras de cadastros por dia não
 * entrega — ali cada dia é um número solto.
 */
final class UsersGrowthChart extends BaseTimeSeriesWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 7];

    public function getHeading(): string
    {
        return __('admin.dashboards.growth.chart_users_heading');
    }

    protected function emptyIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedUserPlus;
    }

    /**
     * @return list<ChartSeries>
     */
    protected function chartSeries(Period $period): array
    {
        $base = (float) User::query()->where('created_at', '<', $period->start())->count();

        return [
            ChartSeries::make(
                __('admin.dashboards.growth.chart_users_total'),
                Metric::count(fn () => User::query(), $period)->cumulativeSeries($base),
                StatusPalette::Neutral,
            ),
        ];
    }
}
