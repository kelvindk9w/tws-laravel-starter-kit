@props([
    'title' => null,
])

@php
    // Âncoras da landing declaradas UMA vez: header do desktop e drawer mobile.
    $landingNav = [
        ['href' => url('/#recursos'), 'label' => __('landing.nav.features')],
        ['href' => url('/#horas'), 'label' => __('landing.nav.hours')],
        ['href' => url('/#stack'), 'label' => __('landing.nav.stack')],
        ['href' => route('ui.showcase'), 'label' => __('landing.nav.components')],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? platform()->name }}</title>

    {{-- Branding 100% via platform() (ADR-007/010). --}}
    {{-- Override de marca opcional (.env PLATFORM_PRIMARY_COLOR). Vazio =
     identidade monocromática dos tokens (theme.css) — ver README. --}}
    @if (platform()->primaryColor !== null)
        <style>:root { --brand: {{ platform()->primaryColor }}; }</style>
    @endif

    @include('partials.theme-script')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @auth data-authenticated @endauth class="min-h-screen bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <header class="sticky top-0 z-40 border-b border-border bg-white/80 backdrop-blur dark:bg-gray-950/80">
        <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:gap-6">
            <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-2 font-display font-semibold tracking-tight">
                @if (platform()->logoUrl)
                    <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
                @else
                    <span class="inline-block h-8 w-8 shrink-0 rounded-lg bg-brand"></span>
                @endif
                <span class="truncate">{{ platform()->name }}</span>
            </a>

            <nav class="hidden flex-1 items-center gap-1 text-sm sm:flex">
                @foreach ($landingNav as $item)
                    <a href="{{ $item['href'] }}" class="rounded-md px-3 py-1.5 text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-surface-sunken hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <div class="ms-auto flex items-center gap-2 sm:ms-0">
                <div class="hidden sm:block"><x-locale-switcher /></div>
                <div class="hidden sm:block"><x-theme-toggle /></div>

                {{-- Um CTA primário no header, e só. O resto vive no drawer
                     (mobile) ou como link de texto. --}}
                @auth
                    <x-button :href="route('dashboard')" size="sm" class="hidden sm:inline-flex!">{{ __('landing.nav.dashboard') }}</x-button>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-md px-3 py-1.5 text-sm text-gray-600 underline-offset-4 transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 hover:underline sm:inline-block dark:text-gray-300 dark:hover:text-white">{{ __('landing.nav.login') }}</a>
                    <x-button :href="route('register')" size="sm" class="hidden sm:inline-flex!">{{ __('landing.nav.register') }}</x-button>
                @endauth

                {{-- Hambúrguer: só no mobile (a nav e os CTAs vão para o drawer). --}}
                <button
                    type="button"
                    data-modal-open="landing-menu"
                    aria-label="{{ __('landing.nav.open_menu') }}"
                    class="-me-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-600 transition-colors duration-150 ease-(--ease-out) hover:bg-surface-sunken sm:hidden dark:text-gray-300"
                >
                    <x-ui-icon name="bars-3" class="h-6 w-6" />
                </button>
            </div>
        </div>
    </header>

    <x-drawer id="landing-menu" :title="__('landing.nav.menu')" class="sm:hidden">
        <nav class="flex flex-col gap-1">
            @foreach ($landingNav as $item)
                <a href="{{ $item['href'] }}" data-modal-close class="flex min-h-11 items-center rounded-lg px-3 py-2.5 text-sm text-gray-700 transition-colors duration-150 ease-(--ease-out) hover:bg-surface-sunken dark:text-gray-200">{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <x-slot:footer>
            <div class="flex flex-col gap-3">
                @auth
                    <x-button :href="route('dashboard')" class="w-full">{{ __('landing.nav.dashboard') }}</x-button>
                @else
                    <x-button :href="route('register')" class="w-full">{{ __('landing.nav.register') }}</x-button>
                    <x-button :href="route('login')" variant="secondary" class="w-full">{{ __('landing.nav.login') }}</x-button>
                @endauth
                <div class="flex items-center justify-between gap-3 pt-1">
                    <x-locale-switcher />
                    <x-theme-toggle />
                </div>
            </div>
        </x-slot:footer>
    </x-drawer>

    {{ $slot }}

    <footer class="border-t border-border">
        {{-- Contraste AA no rodapé: era text-gray-400 (2,60:1 sobre branco) e
             dark:text-gray-500 (4,16:1 sobre gray-950) — os dois reprovam.
             --color-text-muted é gray-500/gray-400 por tema: passa nos dois. --}}
        <div class="mx-auto flex max-w-6xl flex-wrap items-start justify-between gap-x-10 gap-y-6 px-4 py-10 text-sm text-text-muted">
            <div>
                <p class="font-display font-semibold tracking-tight text-gray-900 dark:text-gray-200">{{ platform()->name }}</p>
                <p class="mt-1 max-w-xs">{{ __('landing.footer.tagline') }}</p>
            </div>

            <nav aria-label="{{ __('landing.footer.links_heading') }}" class="flex flex-col gap-2">
                <p class="text-caption font-semibold uppercase tracking-widest text-text-muted">{{ __('landing.footer.links_heading') }}</p>
                <a href="{{ route('ui.showcase') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.showcase') }}</a>
                <a href="{{ route('login') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.demo') }}</a>
                <a href="{{ url('/#contato') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.contact') }}</a>
                <a href="{{ url('/api/health') }}" class="transition-colors duration-150 ease-(--ease-out) hover:text-gray-900 dark:hover:text-gray-200">{{ __('landing.footer.api_status') }}</a>
            </nav>

            <div class="flex flex-col items-start gap-1 sm:items-end">
                <p>
                    {{ __('landing.footer.rights', ['year' => date('Y'), 'company' => platform()->companyName]) }}
                    @if (platform()->companyUrl)
                        · {{ __('landing.footer.developed_by') }}
                        <a href="{{ platform()->companyUrl }}" target="_blank" rel="noopener" class="hover:text-gray-900 dark:hover:text-gray-200">{{ platform()->companyName }}</a>
                    @endif
                </p>
                <p>
                    {{ __('ui.footer.operated_by', ['platform' => platform()->name]) }}
                    @if (platform()->supportEmail)
                        · {{ __('ui.footer.support') }}:
                        <a href="mailto:{{ platform()->supportEmail }}" class="hover:text-gray-900 dark:hover:text-gray-200">{{ platform()->supportEmail }}</a>
                    @endif
                </p>
            </div>
        </div>
    </footer>

    {{-- Flash de sessão (ex.: formulário de contato enviado) → toast do kit;
         renderizado já visível, o auto-esconder vive em resources/js/ui.js. --}}
    <x-flash-toast />
</body>
</html>
