<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Growth;

use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Logging\Models\RequestLog;
use App\Filament\Widgets\Support\BaseStatsWidget;
use App\Filament\Widgets\Support\Metric;
use App\Filament\Widgets\Support\MetricFormat;
use App\Filament\Widgets\Support\MetricStat;
use App\Filament\Widgets\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Adoção e saúde da plataforma em quatro números: quem chegou, quanto quebrou,
 * quão rápido respondeu e quantas credenciais novas foram emitidas.
 *
 * Dois deles são INVERTIDOS (`->inverted()`): em taxa de erro e latência,
 * subir é ruim — e a cor tem de dizer isso sem legenda.
 */
final class GrowthStats extends BaseStatsWidget
{
    /**
     * @return list<MetricStat|Stat>
     */
    protected function metrics(Period $period): array
    {
        $requisicoes = Metric::count(fn () => RequestLog::query(), $period);
        $erros = Metric::count(
            fn () => RequestLog::query()->where('http_status_response', '>=', 400),
            $period,
        );

        return [
            MetricStat::make(
                __('admin.dashboards.growth.new_users'),
                Metric::count(fn () => User::query(), $period),
            )
                ->icon(Heroicon::OutlinedUserPlus)
                ->hint(__('admin.dashboards.growth.new_users_hint')),

            MetricStat::make(
                __('admin.dashboards.growth.error_rate'),
                Metric::ratio($erros, $requisicoes),
            )
                ->format(MetricFormat::Percent)
                ->deltaInPoints()
                ->inverted()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->hint(__('admin.dashboards.growth.error_rate_hint')),

            MetricStat::make(
                __('admin.dashboards.growth.latency'),
                Metric::average(
                    fn () => RequestLog::query()->whereNotNull('duration_ms'),
                    'duration_ms',
                    $period,
                ),
            )
                ->format(MetricFormat::Milliseconds)
                ->inverted()
                ->icon(Heroicon::OutlinedBolt)
                ->hint(__('admin.dashboards.growth.latency_hint')),

            MetricStat::make(
                __('admin.dashboards.growth.new_api_keys'),
                Metric::count(fn () => ApiKey::query(), $period),
            )
                ->icon(Heroicon::OutlinedKey)
                ->hint(__('admin.dashboards.growth.new_api_keys_hint')),
        ];
    }
}
