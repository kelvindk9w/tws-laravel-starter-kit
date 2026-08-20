<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('mail.verification_code.subject', ['platform' => platform()->name]) }}</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a;">
    <h1>{{ platform()->name }}</h1>

    <p>{{ __('mail.verification_code.intro') }}</p>

    <p style="font-size: 2rem; font-weight: 700; letter-spacing: .5rem;">{{ $code }}</p>

    <p>{{ __('mail.verification_code.expires', ['minutes' => $expiresInMinutes]) }}</p>

    <p style="color: #64748b; font-size: .875rem;">{{ __('mail.verification_code.ignore') }}</p>
</body>
</html>
