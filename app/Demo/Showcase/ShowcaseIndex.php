<?php

declare(strict_types=1);

namespace App\Demo\Showcase;

/**
 * Índice da vitrine de componentes (/ui): âncoras agrupadas, na ORDEM do
 * documento — um índice que não segue a página é um mapa de outra cidade.
 *
 * Mesmo formato de grupo do menu lateral do painel
 * (App\Livewire\Support\Navigation): a vitrine usa o MESMO componente
 * <x-side-nav>.
 */
final class ShowcaseIndex
{
    /**
     * @return list<array{label: string, items: list<array{label: string, href: string, anchor: string, active: bool}>}>
     */
    public static function groups(): array
    {
        /** @var array<string, string> $categories */
        $categories = __('showcase.categories');

        $groups = [
            'foundations' => ['theme'],
            'components' => ['buttons', 'alerts', 'badges', 'forms', 'cards', 'modal', 'toast'],
            'states' => ['empty_state', 'loading'],
            'data' => ['data_display', 'navigation', 'form_patterns'],
        ];

        $result = [];

        foreach ($groups as $group => $anchors) {
            $result[] = [
                'label' => __('showcase.groups.'.$group),
                'items' => array_values(array_map(static fn (string $anchor): array => [
                    'label' => $categories[$anchor],
                    'href' => '#'.$anchor,
                    'anchor' => $anchor,
                    'active' => false,
                ], array_filter($anchors, static fn (string $anchor): bool => isset($categories[$anchor])))),
            ];
        }

        return $result;
    }
}
