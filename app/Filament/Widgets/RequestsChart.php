<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Core\Logging\Models\RequestLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Requisições por dia nos últimos 30 dias (crítica de design #3).
 *
 * Fonte: request_logs — a mesma trilha de auditoria do ADR-004. Para que o
 * gráfico tenha conteúdo em QUALQUER instalação, o RequestLogSeeder semeia
 * ~30 dias de tráfego realista (append-only, com redaction).
 *
 * O agrupamento é feito em PHP, não em SQL, de propósito: a suíte roda em
 * SQLite e a aplicação em PostgreSQL, e funções de data divergem entre os
 * dois. O volume da janela (30 dias) não justifica SQL específico por driver.
 */
class RequestsChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /**
     * Janela do gráfico, em dias.
     */
    private const DIAS = 30;

    public function getHeading(): ?string
    {
        return __('admin.dashboard.chart_heading');
    }

    public function getDescription(): ?string
    {
        return __('admin.dashboard.chart_description');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $inicio = now()->startOfDay()->subDays(self::DIAS - 1);

        // Esqueleto com TODOS os dias: dia sem tráfego vira zero explícito,
        // não um buraco na linha.
        $porDia = [];

        for ($i = 0; $i < self::DIAS; $i++) {
            $porDia[$inicio->copy()->addDays($i)->toDateString()] = ['total' => 0, 'erros' => 0];
        }

        RequestLog::query()
            ->where('created_at', '>=', $inicio)
            ->get(['created_at', 'http_status_response'])
            ->each(function (RequestLog $log) use (&$porDia): void {
                $dia = $log->created_at?->toDateString();

                if ($dia === null || ! isset($porDia[$dia])) {
                    return;
                }

                $porDia[$dia]['total']++;

                if (($log->http_status_response ?? 0) >= 400) {
                    $porDia[$dia]['erros']++;
                }
            });

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.chart_requests'),
                    'data' => array_values(array_map(fn (array $d): int => $d['total'], $porDia)),
                    'borderColor' => '#71717a',
                    'backgroundColor' => 'rgba(113, 113, 122, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('admin.dashboard.chart_errors'),
                    'data' => array_values(array_map(fn (array $d): int => $d['erros'], $porDia)),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(
                fn (string $dia): string => Carbon::parse($dia)->format('d/m'),
                array_keys($porDia),
            ),
        ];
    }
}
