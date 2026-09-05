<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Overview;

use App\Core\Logging\Models\RequestLog;
use App\Filament\Widgets\Support\BaseCompositionWidget;
use App\Filament\Widgets\Support\ChartSlice;
use App\Filament\Widgets\Support\Period;
use App\Filament\Widgets\Support\StatusPalette;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * De que é feito o tráfego do período: sucesso, redirecionamento, erro do
 * cliente e erro do servidor. Aqui a cor TEM significado (é a StatusPalette,
 * não uma paleta decorativa) — 5xx vermelho é a fatia que ninguém quer ver
 * crescer.
 */
final class RequestsStatusChart extends BaseCompositionWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 4];

    protected string $chartType = 'doughnut';

    public function getHeading(): string
    {
        return __('admin.dashboards.overview.chart_status_heading');
    }

    protected function emptyIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedShieldCheck;
    }

    /**
     * @return list<ChartSlice>
     */
    protected function chartSlices(Period $period): array
    {
        $faixas = [
            ['key' => 'success', 'min' => 200, 'max' => 299, 'palette' => StatusPalette::Success],
            ['key' => 'redirect', 'min' => 300, 'max' => 399, 'palette' => StatusPalette::Neutral],
            ['key' => 'client_error', 'min' => 400, 'max' => 499, 'palette' => StatusPalette::Warning],
            ['key' => 'server_error', 'min' => 500, 'max' => 599, 'palette' => StatusPalette::Danger],
        ];

        return array_map(
            fn (array $faixa): ChartSlice => ChartSlice::make(
                __('admin.dashboards.overview.status_'.$faixa['key']),
                (float) RequestLog::query()
                    ->where('created_at', '>=', $period->start())
                    ->whereBetween('http_status_response', [$faixa['min'], $faixa['max']])
                    ->count(),
                $faixa['palette'],
            ),
            $faixas,
        );
    }
}
