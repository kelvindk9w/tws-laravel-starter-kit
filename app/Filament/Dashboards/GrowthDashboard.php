<?php

declare(strict_types=1);

namespace App\Filament\Dashboards;

use App\Filament\Widgets\Growth\GrowthStats;
use App\Filament\Widgets\Growth\RecentProjectsTable;
use App\Filament\Widgets\Growth\RequestsGoalProgress;
use App\Filament\Widgets\Growth\TopEndpointsChart;
use App\Filament\Widgets\Growth\UsersGrowthChart;

/**
 * VARIANTE B — "Crescimento & API" (SaaS).
 *
 * Público: o dono técnico do produto, acompanhando adoção da API e saúde da
 * plataforma. Troca "quantos temos" por "para onde está indo": curva
 * acumulada, taxa de erro, latência, ranking de uso e uma meta com ritmo.
 *
 * Grade (12 colunas no desktop):
 *   [ KPIs: novos usuários · erro % · latência · chaves ............. 12 ]
 *   [ Crescimento acumulado ................ 7 ][ Top endpoints ..... 5 ]
 *   [ Meta do mês (progresso) .............. 4 ][ Projetos recentes . 8 ]
 */
final class GrowthDashboard extends BaseDashboard
{
    public static function variant(): string
    {
        return 'growth';
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [
            GrowthStats::class,
            UsersGrowthChart::class,
            TopEndpointsChart::class,
            RequestsGoalProgress::class,
            RecentProjectsTable::class,
        ];
    }
}
