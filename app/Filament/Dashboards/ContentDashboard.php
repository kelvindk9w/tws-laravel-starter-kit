<?php

declare(strict_types=1);

namespace App\Filament\Dashboards;

use App\Filament\Widgets\Content\ContentStats;
use App\Filament\Widgets\Content\LatestProducts;
use App\Filament\Widgets\Content\SubmissionsInbox;
use App\Filament\Widgets\Content\UploadsByTypeChart;
use App\Filament\Widgets\Content\UploadsPerDayChart;

/**
 * VARIANTE C — "Conteúdo & Operação" (E-commerce/Ops).
 *
 * Público: quem cuida do catálogo e do que entra pelo produto — arquivos e
 * mensagens. É a variante do dia a dia: menos tendência, mais fila de
 * trabalho, com o que a camada de segurança barrou sempre no topo.
 *
 * Grade (12 colunas no desktop):
 *   [ KPIs: produtos · uploads · volume · bloqueadas ................ 12 ]
 *   [ Tipos de arquivo (doughnut) .......... 4 ][ Entrada por dia ... 8 ]
 *   [ Últimos produtos ..................... 6 ][ Fila de entrada ... 6 ]
 */
final class ContentDashboard extends BaseDashboard
{
    public static function variant(): string
    {
        return 'content';
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ContentStats::class,
            UploadsByTypeChart::class,
            UploadsPerDayChart::class,
            LatestProducts::class,
            SubmissionsInbox::class,
        ];
    }
}
