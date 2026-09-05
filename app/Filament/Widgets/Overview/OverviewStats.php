<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Overview;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Logging\Models\RequestLog;
use App\Core\Tenancy\Models\Project;
use App\Filament\Widgets\Support\BaseStatsWidget;
use App\Filament\Widgets\Support\Metric;
use App\Filament\Widgets\Support\MetricStat;
use App\Filament\Widgets\Support\Period;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Os quatro números de abertura do painel: tamanho da base, movimento da API,
 * credenciais vivas e projetos. Cada card mostra o TOTAL acumulado e compara
 * o que ENTROU no período com o período anterior — é a diferença entre "temos
 * 1.240 usuários" e "estamos crescendo".
 */
final class OverviewStats extends BaseStatsWidget
{
    /**
     * @return list<MetricStat|Stat>
     */
    protected function metrics(Period $period): array
    {
        return [
            MetricStat::make(
                __('admin.dashboards.overview.users'),
                Metric::count(fn () => User::query(), $period),
            )
                ->headline((float) User::query()->count())
                ->icon(Heroicon::OutlinedUsers)
                ->hint(__('admin.dashboards.overview.users_hint')),

            MetricStat::make(
                __('admin.dashboards.overview.requests'),
                Metric::count(fn () => RequestLog::query(), $period),
            )
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->hint(__('admin.dashboards.overview.requests_hint')),

            MetricStat::make(
                __('admin.dashboards.overview.api_keys'),
                Metric::count(fn () => ApiKey::query(), $period),
            )
                ->headline((float) ApiKey::query()->where('status', ApiKeyStatus::Active->value)->count())
                ->icon(Heroicon::OutlinedKey)
                ->hint(__('admin.dashboards.overview.api_keys_hint')),

            MetricStat::make(
                __('admin.dashboards.overview.projects'),
                Metric::count(fn () => Project::query(), $period),
            )
                ->headline((float) Project::query()->count())
                ->icon(Heroicon::OutlinedFolder)
                ->hint(__('admin.dashboards.overview.projects_hint')),
        ];
    }
}
