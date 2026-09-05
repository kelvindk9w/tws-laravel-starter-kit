<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Content;

use App\Core\Uploads\Models\Upload;
use App\Filament\Widgets\Support\BaseCompositionWidget;
use App\Filament\Widgets\Support\ChartSlice;
use App\Filament\Widgets\Support\Period;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Composição do acervo por FAMÍLIA de tipo (imagem, PDF, documento, outros).
 *
 * DIVERGÊNCIA CONSCIENTE da pesquisa, que propunha "produtos por categoria":
 * a tabela `products` do kit não tem coluna de categoria, e inventar uma
 * migration só para colorir um doughnut seria inventar domínio para servir a
 * tela. O tipo do upload é dado REAL (MIME detectado por magic bytes na Fase
 * 5) e responde a mesma pergunta de composição.
 *
 * Cores da escala neutra: aqui a fatia não tem significado de status — verde
 * para "imagem" seria decoração.
 */
final class UploadsByTypeChart extends BaseCompositionWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 2, 'xl' => 4];

    protected string $chartType = 'doughnut';

    public function getHeading(): string
    {
        return __('admin.dashboards.content.chart_types_heading');
    }

    protected function emptyIcon(): string|BackedEnum
    {
        return Heroicon::OutlinedDocumentDuplicate;
    }

    /**
     * @return list<ChartSlice>
     */
    protected function chartSlices(Period $period): array
    {
        $familias = ['image' => 0, 'pdf' => 0, 'document' => 0, 'other' => 0];

        Upload::query()
            ->where('created_at', '>=', $period->start())
            ->get(['mime'])
            ->each(function (Upload $upload) use (&$familias): void {
                $familias[self::family((string) $upload->mime)]++;
            });

        $fatias = [];

        foreach ($familias as $familia => $total) {
            if ($total > 0) {
                $fatias[] = ChartSlice::make(__('admin.dashboards.content.type_'.$familia), (float) $total);
            }
        }

        return $fatias;
    }

    private static function family(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            $mime === 'application/pdf' => 'pdf',
            str_contains($mime, 'word') || str_contains($mime, 'sheet') || str_starts_with($mime, 'text/') => 'document',
            default => 'other',
        };
    }
}
