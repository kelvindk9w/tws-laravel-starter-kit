<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Content;

use App\Core\Catalog\Models\Product;
use App\Core\Showcase\Models\FormSubmission;
use App\Core\Uploads\Models\Upload;
use App\Filament\Widgets\Support\BaseStatsWidget;
use App\Filament\Widgets\Support\Metric;
use App\Filament\Widgets\Support\MetricFormat;
use App\Filament\Widgets\Support\MetricStat;
use App\Filament\Widgets\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * O dia a dia de quem cuida do conteúdo: catálogo, arquivos que entram,
 * mensagens recebidas e o que a camada de segurança barrou.
 *
 * O card de bloqueios é INVERTIDO: crescer é notícia ruim, e a cor diz isso.
 */
final class ContentStats extends BaseStatsWidget
{
    /**
     * @return list<MetricStat|Stat>
     */
    protected function metrics(Period $period): array
    {
        return [
            MetricStat::make(
                __('admin.dashboards.content.products'),
                Metric::count(fn () => Product::query(), $period),
            )
                ->headline((float) Product::query()->count())
                ->icon(Heroicon::OutlinedShoppingBag)
                ->hint(__('admin.dashboards.content.products_hint')),

            MetricStat::make(
                __('admin.dashboards.content.uploads'),
                Metric::count(fn () => Upload::query(), $period),
            )
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->hint(__('admin.dashboards.content.uploads_hint')),

            MetricStat::make(
                __('admin.dashboards.content.storage'),
                Metric::sum(fn () => Upload::query(), 'size', $period),
            )
                ->format(MetricFormat::Bytes)
                ->icon(Heroicon::OutlinedCircleStack)
                ->hint(__('admin.dashboards.content.storage_hint')),

            MetricStat::make(
                __('admin.dashboards.content.blocked'),
                Metric::count(fn () => FormSubmission::query()->whereNotNull('blocked_at'), $period),
            )
                ->inverted()
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->hint(__('admin.dashboards.content.blocked_hint')),
        ];
    }
}
