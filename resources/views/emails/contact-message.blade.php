<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('contact.mail.subject_line', ['platform' => platform()->name, 'subject' => $subjectLabel]) }}</title>
</head>
<body style="font-family: ui-sans-serif, system-ui, sans-serif; color: #0f172a;">
    <h1>{{ platform()->name }}</h1>

    <p>{{ __('contact.mail.intro') }}</p>

    <p>
        <strong>{{ __('contact.mail.from') }}:</strong> {{ $senderName }} &lt;{{ $senderEmail }}&gt;<br>
        <strong>{{ __('contact.mail.subject_label') }}:</strong> {{ $subjectLabel }}
    </p>

    <p style="white-space: pre-line;">{{ $messageText }}</p>
</body>
</html>
