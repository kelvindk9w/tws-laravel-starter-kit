<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('panel.nav.dashboard') }} — {{ platform()->name }}</title>

    {{-- Branding 100% via platform() (ADR-007/010): nome, logo e cor primária
         vêm do .env (config/platform.php). Nada hardcoded. --}}
    <style>:root { --brand: {{ platform()->primaryColor }}; }</style>

    {{-- Tema claro/escuro/sistema: aplica a classe ANTES do primeiro paint
         (sem flash). Default = preferência do SO; escolha persiste em
         localStorage (dispositivo) e na conta (users.theme). --}}
    @include('partials.theme-script')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-authenticated class="min-h-screen bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                @if (platform()->logoUrl)
                    <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
                @else
                    <span class="inline-block h-8 w-8 rounded-lg bg-(--brand)"></span>
                @endif
                <span>{{ platform()->name }}</span>
            </a>

            <nav class="flex flex-1 flex-wrap items-center gap-1 text-sm">
                <a href="{{ route('dashboard') }}" @class(['rounded-md px-3 py-1.5', 'bg-(--brand) text-white' => request()->routeIs('dashboard'), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs('dashboard')])>{{ __('panel.nav.dashboard') }}</a>
                <a href="{{ route('panel.api-keys') }}" @class(['rounded-md px-3 py-1.5', 'bg-(--brand) text-white' => request()->routeIs('panel.api-keys'), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs('panel.api-keys')])>{{ __('panel.nav.api_keys') }}</a>
                <a href="{{ route('panel.projects') }}" @class(['rounded-md px-3 py-1.5', 'bg-(--brand) text-white' => request()->routeIs('panel.projects'), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs('panel.projects')])>{{ __('panel.nav.projects') }}</a>
                <a href="{{ route('panel.notifications') }}" @class(['rounded-md px-3 py-1.5', 'bg-(--brand) text-white' => request()->routeIs('panel.notifications'), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs('panel.notifications')])>{{ __('panel.nav.notifications') }}</a>
                <a href="{{ route('panel.profile') }}" @class(['rounded-md px-3 py-1.5', 'bg-(--brand) text-white' => request()->routeIs('panel.profile'), 'hover:bg-gray-100 dark:hover:bg-gray-800' => ! request()->routeIs('panel.profile')])>{{ __('panel.nav.profile') }}</a>
            </nav>

            <div class="flex items-center gap-3">
                <x-locale-switcher />
                <x-theme-toggle />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-900 dark:hover:text-gray-100">{{ __('auth.ui.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
