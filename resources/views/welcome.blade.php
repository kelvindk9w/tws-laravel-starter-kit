<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ platform()->name }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh;
               display: flex; align-items: center; justify-content: center;
               background: #0f172a; color: #e2e8f0; }
        main { text-align: center; padding: 2rem; max-width: 40rem; }
        h1 { font-size: 2.25rem; margin-bottom: .5rem; }
        p { color: #94a3b8; }
        footer { margin-top: 3rem; font-size: .8rem; color: #64748b; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <main>
        @if (platform()->logoUrl)
            <img src="{{ platform()->logoUrl }}" alt="{{ platform()->name }}" height="48">
        @endif

        <h1>{{ __('ui.welcome.title', ['platform' => platform()->name]) }}</h1>
        <p>{{ __('ui.welcome.subtitle') }}</p>

        <footer>
            <p>
                {{ __('ui.footer.operated_by', ['platform' => platform()->name]) }}
                @if (platform()->supportEmail)
                    · {{ __('ui.footer.support') }}:
                    <a href="mailto:{{ platform()->supportEmail }}">{{ platform()->supportEmail }}</a>
                @endif
            </p>
        </footer>
    </main>
</body>
</html>
