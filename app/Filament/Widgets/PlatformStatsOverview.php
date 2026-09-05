<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Logging\Models\RequestLog;
use App\Core\Showcase\Models\FormSubmission;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Números de entrada do /admin (crítica de design #3: o painel abria com o
 * AccountWidget de fábrica sozinho em uma tela vazia, num produto que já
 * registra logs, chaves, uploads e submissões).
 *
 * Todo dado vem de tabela real — nada mocado. As janelas (24h/7 dias) são
 * calculadas em UTC, como o resto do sistema (ADR-010).
 */
class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $ultimas24h = now()->subDay();
        $ultimos7dias = now()->subDays(7);

        $requisicoes24h = RequestLog::query()->where('created_at', '>=', $ultimas24h)->count();

        $erros24h = RequestLog::query()
            ->where('created_at', '>=', $ultimas24h)
            ->where('http_status_response', '>=', 400)
            ->count();

        return [
            Stat::make(__('admin.dashboard.users_total'), User::query()->count())
                ->description(__('admin.dashboard.users_total_hint'))
                ->icon(Heroicon::OutlinedUsers),

            Stat::make(
                __('admin.dashboard.users_new'),
                User::query()->where('created_at', '>=', $ultimos7dias)->count(),
            )
                ->description(__('admin.dashboard.users_new_hint'))
                ->icon(Heroicon::OutlinedUserPlus),

            Stat::make(__('admin.dashboard.requests_24h'), $requisicoes24h)
                ->description(__('admin.dashboard.requests_24h_hint'))
                ->icon(Heroicon::OutlinedArrowsRightLeft),

            Stat::make(__('admin.dashboard.errors_24h'), $erros24h)
                ->description(__('admin.dashboard.errors_24h_hint'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                // Erro é exceção: só pinta de vermelho quando existe.
                ->color($erros24h > 0 ? 'danger' : 'gray'),

            Stat::make(
                __('admin.dashboard.api_keys_active'),
                ApiKey::query()->where('status', ApiKeyStatus::Active->value)->count(),
            )
                ->description(__('admin.dashboard.api_keys_active_hint'))
                ->icon(Heroicon::OutlinedKey),

            Stat::make(
                __('admin.dashboard.submissions_recent'),
                FormSubmission::query()->where('created_at', '>=', $ultimos7dias)->count(),
            )
                ->description(__('admin.dashboard.submissions_recent_hint'))
                ->icon(Heroicon::OutlinedInboxArrowDown),
        ];
    }
}
