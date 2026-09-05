<?php

declare(strict_types=1);

// Transactional email strings (en). Every string goes through __() — ADR-007.

return [

    // Advance warning of API key expiration by inactivity (ADR-006).
    'api_key_inactivity' => [
        'subject' => ':platform — Your API key will be deactivated due to inactivity',
        'intro' => 'The API key ":name" (:code, :key) has not been used and will be automatically deactivated due to inactivity.',
        'expires' => 'The deactivation happens in :days days.',
        'action' => 'To keep it active, simply make an authenticated request with it. If you no longer need it, we recommend revoking it in the dashboard.',
        'ignore' => 'If you do not recognize this key, revoke it immediately and rotate your credentials.',
    ],

    // Verification code (email 2FA — ADR-006).
    'verification_code' => [
        'subject' => ':platform — Your verification code',
        'intro' => 'Use the code below to confirm the requested action. It is single-use.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'If you did not request this action, ignore this email and consider changing your password.',
    ],

    // Password reset (QA bug #9 — used to arrive in the framework's English).
    'password_reset' => [
        'subject' => ':platform — Password reset',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expires' => 'This link expires in :minutes minutes.',
        'ignore' => 'If you did not request a password reset, no further action is required.',
    ],

];
