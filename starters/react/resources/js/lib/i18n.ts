import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { Translations } from '@/types';

/**
 * Traduções nas páginas React — os MESMOS arquivos de tradução do Laravel
 * (lang/{pt_BR,en,es}), enviados pelo servidor uma vez por idioma
 * (App\Support\FrontTranslations). Nenhum texto escrito no TypeScript.
 *
 * `t('auth.ui.login_title')` e `t('auth.two_factor.intro', { email, minutes })`,
 * com as substituições no formato do Laravel (`:nome`, `:Nome`, `:NOME`).
 * Chave que não existe volta a própria chave — visível na tela, como no
 * `__()` do Laravel.
 */
export type Replacements = Record<string, string | number>;

export function translate(
    translations: Translations,
    key: string,
    replacements: Replacements = {},
): string {
    const value = key
        .split('.')
        .reduce<unknown>(
            (node, segment) =>
                node !== null && typeof node === 'object'
                    ? (node as Record<string, unknown>)[segment]
                    : undefined,
            translations,
        );

    if (typeof value !== 'string') {
        return key;
    }

    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((line, [name, raw]) => {
            const text = String(raw);

            return line
                .replaceAll(`:${name.toUpperCase()}`, text.toUpperCase())
                .replaceAll(
                    `:${name.charAt(0).toUpperCase()}${name.slice(1)}`,
                    text.charAt(0).toUpperCase() + text.slice(1),
                )
                .replaceAll(`:${name}`, text);
        }, value);
}

export function useTrans(): {
    t: (key: string, replacements?: Replacements) => string;
    locale: string;
} {
    const { translations, app } = usePage().props;

    const t = useCallback(
        (key: string, replacements?: Replacements) =>
            translate(translations, key, replacements),
        [translations],
    );

    return { t, locale: app.locale };
}
