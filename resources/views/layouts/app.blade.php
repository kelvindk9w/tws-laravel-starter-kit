@php
    // Navegação do painel declarada UMA vez: o header do desktop e o drawer do
    // mobile leem a mesma lista (dois markups, uma verdade).
    $panelNav = [
        ['route' => 'dashboard', 'label' => __('panel.nav.dashboard'), 'icon' => 'squares-2x2'],
        ['route' => 'panel.api-keys', 'label' => __('panel.nav.api_keys'), 'icon' => 'key'],
        ['route' => 'panel.projects', 'label' => __('panel.nav.projects'), 'icon' => 'folder'],
        ['route' => 'panel.notifications', 'label' => __('panel.nav.notifications'), 'icon' => 'bell'],
        ['route' => 'panel.profile', 'label' => __('panel.nav.profile'), 'icon' => 'user-circle'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('panel.nav.dashboard') }} — {{ platform()->name }}</title>

    {{-- Branding 100% via platform() (ADR-007/010): nome, logo e cor primária
         vêm do .env (config/platform.php). Nada hardcoded. --}}
    {{-- Override de marca opcional (.env PLATFORM_PRIMARY_COLOR). Vazio =
     identidade monocromática dos tokens (theme.css) — ver README. --}}
    @if (platform()->primaryColor !== null)
        <style>:root { --brand: {{ platform()->primaryColor }}; }</style>
    @endif

    {{-- Tema claro/escuro/sistema: aplica a classe ANTES do primeiro paint
         (sem flash). Default = preferência do SO; escolha persiste em
         localStorage (dispositivo) e na conta (users.theme). --}}
    @include('partials.theme-script')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-authenticated class="min-h-screen bg-surface-sunken text-gray-900 antialiased dark:text-gray-100">
    <header class="border-b border-border bg-surface">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3 sm:gap-6">
            {{-- Hambúrguer: SÓ no mobile. Abaixo de sm: a nav inteira mora no
                 drawer — antes ela empilhava sobre a logo e comia 29% da
                 altura da tela em toda página autenticada. --}}
            <button
                type="button"
                data-modal-open="panel-menu"
                aria-label="{{ __('panel.nav.open_menu') }}"
                class="-ms-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-surface-sunken sm:hidden dark:text-gray-300"
            >
                <x-ui-icon name="bars-3" class="h-6 w-6" />
            </button>

            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2 font-display font-semibold tracking-tight">
                @if (platform()->logoUrl)
                    <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
                @else
                    <span class="inline-block h-8 w-8 shrink-0 rounded-lg bg-brand"></span>
                @endif
                <span class="truncate">{{ platform()->name }}</span>
            </a>

            <nav class="hidden flex-1 items-center gap-1 text-sm sm:flex">
                @foreach ($panelNav as $item)
                    <a
                        href="{{ route($item['route']) }}"
                        @if (request()->routeIs($item['route'])) aria-current="page" @endif
                        @class([
                            'rounded-md px-3 py-1.5 transition-colors duration-150 ease-(--ease-out)',
                            'bg-brand text-brand-foreground' => request()->routeIs($item['route']),
                            'text-gray-600 hover:bg-surface-sunken hover:text-gray-900 dark:text-gray-300 dark:hover:text-white' => ! request()->routeIs($item['route']),
                        ])
                    >{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="ms-auto flex items-center gap-2 sm:ms-0">
                <div class="hidden sm:block"><x-locale-switcher /></div>
                <x-theme-toggle />
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="rounded-md px-3 py-1.5 text-sm text-text-muted transition-colors duration-150 ease-(--ease-out) hover:bg-surface-sunken hover:text-gray-900 dark:hover:text-gray-100">{{ __('auth.ui.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    {{-- Drawer de navegação (mobile). Mesmo motor do modal: Esc, backdrop,
         foco preso e devolvido — resources/js/ui.js. --}}
    <x-drawer id="panel-menu" :title="__('panel.nav.menu')" side="left" class="sm:hidden">
        <nav class="flex flex-col gap-1">
            @foreach ($panelNav as $item)
                <a
                    href="{{ route($item['route']) }}"
                    @if (request()->routeIs($item['route'])) aria-current="page" @endif
                    @class([
                        'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors duration-150 ease-(--ease-out)',
                        'bg-brand text-brand-foreground' => request()->routeIs($item['route']),
                        'text-gray-700 hover:bg-surface-sunken dark:text-gray-200' => ! request()->routeIs($item['route']),
                    ])
                >
                    <x-ui-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <x-slot:footer>
            <div class="flex items-center justify-between gap-3">
                <x-locale-switcher />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm">
                        <x-ui-icon name="arrow-right-on-rectangle" class="h-4 w-4" />
                        {{ __('auth.ui.logout') }}
                    </x-button>
                </form>
            </div>
        </x-slot:footer>
    </x-drawer>

    <main class="mx-auto max-w-5xl px-4 py-8">
        {{ $slot }}
    </main>

    {{-- Flash de sessão (ex.: conta criada) → toast do kit. --}}
    <x-flash-toast />
</body>
</html>
