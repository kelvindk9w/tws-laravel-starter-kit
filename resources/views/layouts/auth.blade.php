<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme-default="{{ auth()->user()?->theme ?? 'system' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ platform()->name }}</title>

    <style>:root { --brand: {{ platform()->primaryColor }}; }</style>

    @include('partials.theme-script')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <main class="w-full max-w-sm">
        <a href="{{ url('/') }}" class="mb-6 flex items-center justify-center gap-2 font-display font-semibold tracking-tight">
            @if (platform()->logoUrl)
                <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" class="h-8 w-auto">
            @else
                <span class="inline-block h-8 w-8 rounded-lg bg-(--brand)"></span>
            @endif
            <span>{{ platform()->name }}</span>
        </a>

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @if (session('status'))
                <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
            @endif

            @if ($errors->any())
                <x-alert type="error" class="mb-4">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
