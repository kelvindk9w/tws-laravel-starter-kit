<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ platform()->name }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh;
               display: flex; align-items: center; justify-content: center;
               background: #0f172a; color: #e2e8f0; }
        main { width: 100%; max-width: 24rem; padding: 2rem; }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
        label { display: block; margin: 1rem 0 .25rem; font-size: .875rem; color: #94a3b8; }
        input { width: 100%; box-sizing: border-box; padding: .6rem .75rem; border-radius: .375rem;
                border: 1px solid #334155; background: #1e293b; color: #e2e8f0; }
        /* Checkbox ("Manter conectado"): não herda o width:100% dos campos de
           texto e fica alinhado ao rótulo, verticalmente centralizado. */
        input[type="checkbox"] { width: auto; margin: 0; }
        label:has(input[type="checkbox"]) { display: flex; align-items: center; gap: .5rem; }
        button { margin-top: 1.25rem; width: 100%; padding: .65rem; border: 0; border-radius: .375rem;
                 background: #38bdf8; color: #0f172a; font-weight: 600; cursor: pointer; }
        .errors { margin-top: 1rem; padding: .75rem; border-radius: .375rem;
                  background: #7f1d1d; color: #fecaca; font-size: .875rem; }
        .status { margin-top: 1rem; padding: .75rem; border-radius: .375rem;
                  background: #14532d; color: #bbf7d0; font-size: .875rem; }
        .links { margin-top: 1.25rem; font-size: .875rem; }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <main>
        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
