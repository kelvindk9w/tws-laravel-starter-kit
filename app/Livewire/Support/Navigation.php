<?php

declare(strict_types=1);

namespace App\Livewire\Support;

/**
 * Mapa de navegação do site — UMA verdade para todos os markups.
 *
 * O cabeçalho do site, o drawer do mobile e o menu lateral do painel
 * mostram os MESMOS itens em três geometrias diferentes. Enquanto cada
 * arquivo Blade declarava a própria lista, "adicionar uma tela" era mexer
 * em três lugares e esquecer um — foi assim que o painel e a landing
 * viraram dois produtos diferentes para o mesmo usuário.
 *
 * Não é um componente Livewire: é o único lugar do app/Livewire onde cabe
 * uma estrutura compartilhada pelas views do painel (as demais camadas —
 * Core, Http, Providers — pertencem ao kit e não conhecem telas).
 *
 * Formato de um grupo:
 *   ['label' => 'Conta', 'items' => [ ...itens... ]]
 *
 * Formato de um item:
 *   ['label' => 'Perfil', 'href' => '/profile', 'icon' => 'user-circle',
 *    'active' => bool, 'anchor' => 'perfil'|null]
 */
final class Navigation
{
    /**
     * Links institucionais do site (cabeçalho público e drawer).
     *
     * @return list<array{label: string, href: string}>
     */
    public static function site(): array
    {
        return [
            ['label' => __('landing.nav.features'), 'href' => url('/#recursos')],
            ['label' => __('landing.nav.hours'), 'href' => url('/#horas')],
            ['label' => __('landing.nav.stack'), 'href' => url('/#stack')],
            ['label' => __('landing.nav.components'), 'href' => route('ui.showcase')],
        ];
    }

    /**
     * "Minha conta" — o menu lateral do painel, agrupado por assunto.
     *
     * @return list<array{label: string, items: list<array{label: string, href: string, icon: string, active: bool}>}>
     */
    public static function account(): array
    {
        $groups = [
            [
                'label' => __('panel.nav.groups.overview'),
                'items' => [
                    ['route' => 'dashboard', 'label' => __('panel.nav.dashboard'), 'icon' => 'squares-2x2'],
                ],
            ],
            [
                'label' => __('panel.nav.groups.development'),
                'items' => [
                    ['route' => 'panel.api-keys', 'label' => __('panel.nav.api_keys'), 'icon' => 'key'],
                    ['route' => 'panel.projects', 'label' => __('panel.nav.projects'), 'icon' => 'folder'],
                ],
            ],
            [
                'label' => __('panel.nav.groups.account'),
                'items' => [
                    ['route' => 'panel.notifications', 'label' => __('panel.nav.notifications'), 'icon' => 'bell'],
                    ['route' => 'panel.profile', 'label' => __('panel.nav.profile'), 'icon' => 'user-circle'],
                    ['route' => 'transaction-password.edit', 'label' => __('panel.nav.transaction_password'), 'icon' => 'lock-closed'],
                ],
            ],
        ];

        return array_map(static fn (array $group): array => [
            'label' => $group['label'],
            'items' => array_map(static fn (array $item): array => [
                'label' => $item['label'],
                'href' => route($item['route']),
                'icon' => $item['icon'],
                'active' => request()->routeIs($item['route']),
            ], $group['items']),
        ], $groups);
    }

    /**
     * Índice do showcase (/ui): âncoras agrupadas, na ORDEM do documento —
     * um índice que não segue a página é um mapa de outra cidade.
     *
     * @return list<array{label: string, items: list<array{label: string, href: string, anchor: string, active: bool}>}>
     */
    public static function showcase(): array
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
