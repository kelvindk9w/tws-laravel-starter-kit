<?php

declare(strict_types=1);

// Authentication e-mails (en): the subjects, built by the twstec/kit-auth mail
// classes, and the body texts (resources/views/mail/messages of the package).
// The application wins: the same key in its lang/ takes precedence (see
// docs/personalizacao.md).

return [

    'verification_code' => [
        'subject' => ':platform — Your verification code',

        // Verification code (email 2FA).
        'preheader' => 'Your code expires in :minutes minutes.',
        'heading' => 'Your verification code',
        'intro' => 'Use the code below to confirm the requested action. It is single-use.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'If you did not request this action, ignore this email and consider changing your password.',
    ],

    'login_code' => [
        'subject' => ':platform — Your sign-in code',

        // Second-factor code for LOGIN (same code email, different purpose).
        'preheader' => 'Your sign-in code expires in :minutes minutes.',
        'heading' => 'Your sign-in code',
        'intro' => 'Your account password was entered correctly and one step is left to finish signing in. If it was you, use the code below. It is single-use.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'Wasn\'t you? Don\'t share this code with anyone and change your password now: whoever tried to sign in knows your current password.',
    ],

    'email_verification' => [
        'subject' => ':platform — Confirm your email',

        // Password reset (QA bug #9 — used to arrive in the framework's English).
        'preheader' => 'One more step: confirm your email to unlock your account.',
        'heading' => 'Confirm your email',
        'intro' => 'Your account has been created. To unlock the dashboard, confirm that this address is yours.',
        'action' => 'Confirm email',
        'expires' => 'This link expires in :minutes minutes. After that, request a new one on the notice screen.',
        'fallback' => 'If the button does not work, copy and paste this address into your browser:',
        'ignore' => 'If you did not create this account, ignore this email: without confirmation it stays locked.',
    ],

    'password_reset' => [
        'subject' => ':platform — Password reset',

        'preheader' => 'Reset link valid for :minutes minutes.',
        'heading' => 'Reset your password',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expires' => 'This link expires in :minutes minutes.',
        'fallback' => 'If the button does not work, copy and paste this address into your browser:',
        'ignore' => 'If you did not request a password reset, no further action is required.',
    ],

];
