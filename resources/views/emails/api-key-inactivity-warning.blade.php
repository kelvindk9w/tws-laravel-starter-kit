<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('mail.api_key_inactivity.subject', ['platform' => platform()->name]) }}</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a;">
    <h1>{{ platform()->name }}</h1>

    <p>{{ __('mail.api_key_inactivity.intro', ['name' => $keyName, 'code' => $keyPublicCode, 'key' => $keyPublicKey]) }}</p>

    <p><strong>{{ __('mail.api_key_inactivity.expires', ['days' => $expiresInDays]) }}</strong></p>

    <p>{{ __('mail.api_key_inactivity.action') }}</p>

    <p style="color: #64748b; font-size: .875rem;">{{ __('mail.api_key_inactivity.ignore') }}</p>
</body>
</html>
