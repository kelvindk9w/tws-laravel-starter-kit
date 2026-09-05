<?php

declare(strict_types=1);

namespace App\Filament\Dashboards;

use App\Filament\Widgets\Overview\LatestSubmissions;
use App\Filament\Widgets\Overview\LatestUploads;
use App\Filament\Widgets\Overview\OverviewStats;
use App\Filament\Widgets\Overview\RequestsStatusChart;
use App\Filament\Widgets\Overview\RequestsTrendChart;

/**
 * VARIANTE A — "Visão Geral" (Analytics).
 *
 * Público: quem abre o painel para saber, em dez segundos, se a plataforma
 * está saudável. É a variante padrão (responde em /admin) porque responde à
 * pergunta mais genérica: quanto temos, quanto entrou, o que quebrou.
 *
 * Grade (12 colunas no desktop):
 *   [ KPIs: usuários · requisições · chaves · projetos ............... 12 ]
 *   [ Requisições por dia (linha + erros) .. 8 ][ Status (doughnut) .. 4 ]
 *   [ Últimas submissões ................... 6 ][ Últimos uploads .... 6 ]
 */
final class OverviewDashboard extends BaseDashboard
{
    public static function variant(): string
    {
        return 'overview';
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [
            OverviewStats::class,
            RequestsTrendChart::class,
            RequestsStatusChart::class,
            LatestSubmissions::class,
            LatestUploads::class,
        ];
    }
}
