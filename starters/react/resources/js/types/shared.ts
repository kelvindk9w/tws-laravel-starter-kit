import type { ReactNode } from 'react';

/**
 * As props que TODA página recebe do servidor
 * (App\Http\Middleware\HandleInertiaRequests). Lista fechada: nada de
 * credencial passa por aqui.
 */
export type User = {
    uuid: string;
    code: string;
    name: string;
    email: string;
    locale: string;
    theme: Theme;
    avatarUrl: string | null;
    emailVerified: boolean;
    hasTransactionPassword: boolean;
    twoFactorEnabled: boolean;
};

export type Theme = 'light' | 'dark' | 'system';

export type Locale = {
    code: string;
    label: string;
    url: string;
};

export type NavItem = {
    label: string;
    href: string;
    icon: string;
    active: boolean;
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};

export type OptionalModule = 'accounts' | 'uploads' | 'admin';

export type Translations = Record<string, unknown>;

export type SharedProps = {
    app: {
        name: string;
        logoUrl: string | null;
        locale: string;
        locales: Locale[];
    };
    auth: {
        user: User | null;
    };
    kit: {
        modules: Record<OptionalModule, boolean>;
    };
    navigation: NavGroup[];
    routes: Record<string, string>;
    flash: {
        status: string | null;
        verification_error: string | null;
    };
    translations: Translations;
    sidebarOpen: boolean;
};

export type BreadcrumbItem = {
    /** Chave de tradução (ex.: `panel.nav.profile`). */
    title: string;
    /** Nome da rota (ex.: `panel.profile`). */
    route: string;
};

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AuthLayoutProps = {
    children?: ReactNode;
    /** Chave de tradução do título. */
    title?: string;
    /** Chave de tradução da descrição. */
    description?: string;
};

export type AppVariant = 'header' | 'sidebar';
