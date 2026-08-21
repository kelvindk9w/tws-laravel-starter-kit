@props([
    'title' => null,
    // Landing é sempre escura (decisão de design); o showcase (/ui) deixa o
    // visitante alternar claro/escuro (toggle persiste em localStorage).
    'forceDark' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" @if ($forceDark) data-force-dark @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? platform()->name }}</title>

    {{-- Branding 100% via platform() (ADR-007/010). --}}
    <style>:root { --brand: {{ platform()->primaryColor }}; }</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <header class="sticky top-0 z-40 border-b border-gray-200 bg-white/80 backdrop-blur dark:border-gray-800 dark:bg-gray-950/80">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-display font-semibold tracking-tight">
                @if (platform()->logoUrl)
                    <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
                @else
                    <span class="inline-block h-8 w-8 rounded-lg bg-(--brand)"></span>
                @endif
                <span>{{ platform()->name }}</span>
            </a>

            {{-- Mobile: a nav ocupa uma linha própria abaixo da logo/ações. --}}
            <nav class="order-last flex w-full flex-wrap items-center gap-1 text-sm sm:order-none sm:w-auto sm:flex-1">
                <a href="{{ url('/#recursos') }}" class="rounded-md px-3 py-1.5 text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">{{ __('landing.nav.features') }}</a>
                <a href="{{ url('/#horas') }}" class="rounded-md px-3 py-1.5 text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">{{ __('landing.nav.hours') }}</a>
                <a href="{{ url('/#stack') }}" class="rounded-md px-3 py-1.5 text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">{{ __('landing.nav.stack') }}</a>
                <a href="{{ route('ui.showcase') }}" class="rounded-md px-3 py-1.5 text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white">{{ __('landing.nav.components') }}</a>
            </nav>

            <div class="flex items-center gap-2">
                <x-locale-switcher />
                @auth
                    <x-button :href="route('dashboard')">{{ __('landing.nav.dashboard') }}</x-button>
                @else
                    <x-button :href="route('login')" variant="ghost">{{ __('landing.nav.login') }}</x-button>
                    <x-button :href="route('register')">{{ __('landing.nav.register') }}</x-button>
                @endauth
            </div>
        </div>
    </header>

    {{ $slot }}

    <footer class="border-t border-gray-200 dark:border-gray-800">
        <div class="mx-auto flex max-w-6xl flex-wrap items-start justify-between gap-x-10 gap-y-6 px-4 py-10 text-sm text-gray-500 dark:text-gray-400">
            <div>
                <p class="font-display font-semibold tracking-tight text-gray-900 dark:text-gray-200">{{ platform()->name }}</p>
                <p class="mt-1 max-w-xs">{{ __('landing.footer.tagline') }}</p>
            </div>

            <nav aria-label="{{ __('landing.footer.links_heading') }}" class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">{{ __('landing.footer.links_heading') }}</p>
                <a href="{{ route('ui.showcase') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.showcase') }}</a>
                <a href="{{ route('login') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.demo') }}</a>
                <a href="{{ url('/api/health') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.api_status') }}</a>
            </nav>

            <div class="flex flex-col items-start gap-1 sm:items-end">
                <p class="text-gray-400 dark:text-gray-500">
                    {{ __('ui.footer.operated_by', ['platform' => platform()->name]) }}
                    @if (platform()->supportEmail)
                        · {{ __('ui.footer.support') }}:
                        <a href="mailto:{{ platform()->supportEmail }}" class="hover:text-gray-900 dark:hover:text-gray-200">{{ platform()->supportEmail }}</a>
                    @endif
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
