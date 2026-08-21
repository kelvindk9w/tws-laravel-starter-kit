<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? platform()->name }}</title>

    {{-- Branding 100% via platform() (ADR-007/010). Landing sempre em tema escuro. --}}
    <style>:root { --brand: {{ platform()->primaryColor }}; }</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-950 text-gray-100 antialiased">
    <header class="sticky top-0 z-40 border-b border-gray-800 bg-gray-950/80 backdrop-blur">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3">
            <a href="{{ url('/') }}" class="flex items-center gap-2 font-semibold">
                @if (platform()->logoUrl)
                    <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
                @else
                    <span class="inline-block h-8 w-8 rounded-lg bg-(--brand)"></span>
                @endif
                <span>{{ platform()->name }}</span>
            </a>

            <nav class="flex flex-1 flex-wrap items-center gap-1 text-sm">
                <a href="{{ url('/#recursos') }}" class="rounded-md px-3 py-1.5 text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('landing.nav.features') }}</a>
                <a href="{{ url('/#stack') }}" class="rounded-md px-3 py-1.5 text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('landing.nav.stack') }}</a>
                <a href="{{ route('ui.showcase') }}" class="rounded-md px-3 py-1.5 text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('landing.nav.components') }}</a>
            </nav>

            <div class="flex items-center gap-2">
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

    <footer class="border-t border-gray-800">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-8 text-sm text-gray-400">
            <div>
                <p class="font-semibold text-gray-200">{{ platform()->name }}</p>
                <p>{{ __('landing.footer.tagline') }}</p>
            </div>
            <div class="flex flex-col items-start gap-1 sm:items-end">
                <a href="{{ route('ui.showcase') }}" class="hover:text-gray-200">{{ __('landing.footer.showcase') }}</a>
                <p class="text-gray-500">
                    {{ __('ui.footer.operated_by', ['platform' => platform()->name]) }}
                    @if (platform()->supportEmail)
                        · {{ __('ui.footer.support') }}:
                        <a href="mailto:{{ platform()->supportEmail }}" class="hover:text-gray-200">{{ platform()->supportEmail }}</a>
                    @endif
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
