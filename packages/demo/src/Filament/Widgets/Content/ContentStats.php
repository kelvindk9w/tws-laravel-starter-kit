<?php

declare(strict_types=1);

namespace Twstec\Kit\Demo\Filament\Widgets\Content;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Twstec\Kit\Admin\Widgets\Support\BaseStatsWidget;
use Twstec\Kit\Admin\Widgets\Support\Metric;
use Twstec\Kit\Admin\Widgets\Support\MetricFormat;
use Twstec\Kit\Admin\Widgets\Support\MetricStat;
use Twstec\Kit\Admin\Widgets\Support\Period;
use Twstec\Kit\Demo\Catalog\Models\Product;
use Twstec\Kit\Demo\Showcase\Models\FormSubmission;
use Twstec\Kit\Uploads\Models\Upload;

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
