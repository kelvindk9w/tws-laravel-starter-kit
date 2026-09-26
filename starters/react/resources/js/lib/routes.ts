import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Endereço de uma rota pelo NOME — o mapa vem do servidor
 * (App\Support\FrontRoutes), só com as rotas que existem nesta instalação.
 * Nenhuma URL escrita no TypeScript.
 *
 * Pedir uma rota que não está no mapa é erro de programação (a rota não
 * existe ou faltou na lista do FrontRoutes): falha alto em vez de montar um
 * link quebrado.
 */
export function useRoute(): {
    route: (name: string) => string;
    has: (name: string) => boolean;
} {
    const { routes } = usePage().props;

    const route = useCallback(
        (name: string): string => {
            const url = routes[name];

            if (url === undefined) {
                throw new Error(`Rota desconhecida no front: ${name}`);
            }

            return url;
        },
        [routes],
    );

    const has = useCallback(
        (name: string): boolean => routes[name] !== undefined,
        [routes],
    );

    return { route, has };
}
