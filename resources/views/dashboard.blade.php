<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.ui.dashboard_title') }} — {{ platform()->name }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh;
               display: flex; align-items: center; justify-content: center;
               background: #0f172a; color: #e2e8f0; }
        main { text-align: center; padding: 2rem; max-width: 40rem; }
        .status { margin: 1rem 0; padding: .75rem; border-radius: .375rem;
                  background: #14532d; color: #bbf7d0; }
        code { color: #38bdf8; }
        a, button { color: #38bdf8; background: none; border: 0; cursor: pointer; font-size: 1rem; }
    </style>
</head>
<body>
    <main>
        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif

        <h1>{{ __('auth.ui.dashboard_greeting', ['name' => auth()->user()->name]) }}</h1>
        <p>{{ __('auth.ui.dashboard_code') }}: <code>{{ auth()->user()->codigo_publico }}</code></p>

        <p>
            <a href="{{ route('transaction-password.edit') }}">{{ __('auth.ui.transaction_password_title') }}</a>
        </p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">{{ __('auth.ui.logout') }}</button>
        </form>
    </main>
</body>
</html>
