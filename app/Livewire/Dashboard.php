<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Core\ApiKeys\Enums\ApiKeyStatus;
use App\Core\ApiKeys\Models\ApiKey;
use App\Core\Auth\Models\User;
use App\Core\Logging\Models\RequestLog;
use App\Core\Tenancy\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Dashboard do painel do usuário (Fase 6 — ADR-005/011).
 *
 * A tela de entrada mostra o que o kit JÁ COLETA sobre a conta: chaves,
 * projetos e o tráfego real da API do tenant (request_logs). Um dashboard que
 * só conta linhas de duas tabelas não prova que a instrumentação existe.
 *
 * Fonte do tráfego: `request_logs.tenant_uuid` = uuid do dono da chave,
 * vinculado pelo middleware ResolveTenant (Fase 4). Log sem tenant não é
 * desta conta e nunca aparece aqui.
 */
final class Dashboard extends Component
{
    /** Janela do gráfico de requisições por dia. */
    private const CHART_DAYS = 30;

    /** Janela da métrica "requisições recentes". */
    private const RECENT_DAYS = 7;

    /** Quantas chamadas aparecem na lista de atividade. */
    private const RECENT_CALLS = 5;

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $tenantUuid = (string) $user->uuid;

        // A janela é contada no fuso de EXIBIÇÃO (o "dia" do gráfico é o dia
        // do usuário, não o dia UTC): sem isso a série ganha um dia a mais na
        // ponta e o primeiro rótulo fica pela metade.
        $timezone = platform()->displayTimezone;
        $today = Carbon::now($timezone)->startOfDay();
        $since = $today->copy()->subDays(self::CHART_DAYS - 1);

        // UMA leitura da janela de 30 dias serve o gráfico E o contador de 7
        // dias: agrupar por dia em PHP mantém a consulta portável entre
        // PostgreSQL (dev/prod) e SQLite (suíte Pest) sem SQL específico de
        // driver — a janela é pequena por definição.
        $window = RequestLog::query()
            ->where('tenant_uuid', $tenantUuid)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->pluck('created_at');

        return view('livewire.dashboard', [
            'user' => $user,
            'activeKeysCount' => ApiKey::query()
                ->where('user_id', $user->id)
                ->where('status', ApiKeyStatus::Active)
                ->count(),
            'projectsCount' => Project::query()
                ->where('user_id', $user->id)
                ->count(),
            'recentRequestsCount' => $window
                ->filter(fn (Carbon $moment): bool => $moment->greaterThanOrEqualTo(
                    $today->copy()->subDays(self::RECENT_DAYS - 1),
                ))
                ->count(),
            'recentRequestsDays' => self::RECENT_DAYS,
            'lastKeyUsedAt' => ApiKey::query()
                ->where('user_id', $user->id)
                ->whereNotNull('last_used_at')
                ->max('last_used_at'),
            'chartDays' => self::CHART_DAYS,
            'chart' => $this->dailySeries($window, $since, $today, $timezone),
            'recentCalls' => RequestLog::query()
                ->where('tenant_uuid', $tenantUuid)
                ->latest('created_at')
                ->limit(self::RECENT_CALLS)
                ->get(['uuid', 'method', 'endpoint', 'status', 'http_status_response', 'created_at']),
        ])->title(__('panel.dashboard.title'));
    }

    /**
     * Série diária COMPLETA da janela: dias sem tráfego entram com zero.
     * Sem isso o gráfico mentiria — ligaria dois dias distantes como se o
     * intervalo não existisse.
     *
     * @param  Collection<int, Carbon>  $moments
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailySeries(Collection $moments, Carbon $since, Carbon $today, string $timezone): array
    {
        $counts = $moments
            ->groupBy(fn (Carbon $moment): string => $moment->copy()->setTimezone($timezone)->toDateString())
            ->map(fn (Collection $day): int => $day->count());

        $labels = [];
        $values = [];

        for ($day = $since->copy(); $day->lessThanOrEqualTo($today); $day->addDay()) {
            $labels[] = $day->format('d/m');
            $values[] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
