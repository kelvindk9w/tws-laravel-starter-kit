<?php

declare(strict_types=1);

namespace App\Filament\Dashboards;

use Filament\Pages\Dashboard as FilamentDashboard;

/**
 * Quem lê config/dashboards.php. É o ÚNICO ponto do painel que sabe quais
 * variantes existem, qual delas é a home do /admin e em que ordem aparecem
 * no menu — o AdminPanelProvider e as próprias páginas perguntam aqui
 * (ADR-007: nada de lista de dashboards hardcodada em código).
 *
 * Desligar uma variante em DASHBOARD_ENABLED remove a PÁGINA do painel: some
 * do menu e a rota deixa de existir (404), porque a classe nem chega a ser
 * registrada. Não é um item escondido com a URL ainda respondendo.
 */
final class DashboardRegistry
{
    /**
     * Variantes habilitadas, na ordem do menu.
     *
     * @return array<string, array{page: class-string, icon: string, sort: int}>
     */
    public static function variants(): array
    {
        /** @var array<string, array{page: class-string, icon?: string, sort?: int}> $catalogo */
        $catalogo = (array) config('dashboards.variants', []);

        $habilitadas = array_map('strval', (array) config('dashboards.enabled', []));

        $variantes = [];

        foreach ($habilitadas as $slug) {
            if (! isset($catalogo[$slug]) || ! class_exists($catalogo[$slug]['page'] ?? '')) {
                continue;
            }

            $variantes[$slug] = [
                'page' => $catalogo[$slug]['page'],
                'icon' => (string) ($catalogo[$slug]['icon'] ?? 'heroicon-o-squares-2x2'),
                'sort' => (int) ($catalogo[$slug]['sort'] ?? 0),
            ];
        }

        uasort($variantes, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $variantes;
    }

    /**
     * Páginas a registrar no painel. Sem NENHUMA variante habilitada, o
     * painel volta ao Dashboard de fábrica do Filament — o /admin nunca fica
     * sem home nem responde 404.
     *
     * @return list<class-string>
     */
    public static function pages(): array
    {
        $paginas = array_values(array_map(
            fn (array $variante): string => $variante['page'],
            self::variants(),
        ));

        return $paginas === [] ? [FilamentDashboard::class] : $paginas;
    }

    public static function isEnabled(string $slug): bool
    {
        return array_key_exists($slug, self::variants());
    }

    /**
     * Slug da variante que responde em /admin. Se o DASHBOARD_DEFAULT
     * apontar para uma variante desligada, a primeira habilitada assume.
     */
    public static function default(): ?string
    {
        $variantes = self::variants();

        if ($variantes === []) {
            return null;
        }

        $padrao = (string) config('dashboards.default', '');

        return isset($variantes[$padrao]) ? $padrao : (string) array_key_first($variantes);
    }

    public static function isDefault(string $slug): bool
    {
        return self::default() === $slug;
    }

    public static function icon(string $slug): string
    {
        return self::variants()[$slug]['icon'] ?? 'heroicon-o-squares-2x2';
    }

    public static function sort(string $slug): int
    {
        return self::variants()[$slug]['sort'] ?? 0;
    }
}
