<?php

declare(strict_types=1);

// Transactional email strings (en). Every string goes through __().
// Message bodies live in resources/views/mail/messages/**, on the single
// <x-email::layouts.kit> layout. See docs/emails.md. The footer shared by
// every email (mail.footer.*) comes from the twstec/kit-foundation package.

return [

    // Advance warning of API key expiration by inactivity.
    'api_key_inactivity' => [
        'subject' => ':platform — Your API key will be deactivated due to inactivity',
        'preheader' => 'An unused key will be deactivated in :days days.',
        'heading' => 'One of your API keys is about to be deactivated',
        'intro' => 'The key below has not been used and will be automatically deactivated due to inactivity.',
        'name_label' => 'Key name',
        'code_label' => 'Public code',
        'key_label' => 'Public key',
        'expires' => 'The deactivation happens in :days days.',
        'action' => 'To keep it active, simply make an authenticated request with it. If you no longer need it, we recommend revoking it in the dashboard.',
        'cta' => 'Open my keys',
        'ignore' => 'If you do not recognize this key, revoke it immediately and rotate your credentials.',
    ],

    // Verification code (email 2FA).
    'verification_code' => [
        'subject' => ':platform — Your verification code',
        'preheader' => 'Your code expires in :minutes minutes.',
        'heading' => 'Your verification code',
        'intro' => 'Use the code below to confirm the requested action. It is single-use.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'If you did not request this action, ignore this email and consider changing your password.',
    ],

    // Second-factor code for LOGIN (same code email, different purpose).
    'login_code' => [
        'subject' => ':platform — Your sign-in code',
        'preheader' => 'Your sign-in code expires in :minutes minutes.',
        'heading' => 'Your sign-in code',
        'intro' => 'Your account password was entered correctly and one step is left to finish signing in. If it was you, use the code below. It is single-use.',
        'expires' => 'This code expires in :minutes minutes.',
        'ignore' => 'Wasn\'t you? Don\'t share this code with anyone and change your password now: whoever tried to sign in knows your current password.',
    ],

    // Password reset (QA bug #9 — used to arrive in the framework's English).
    'email_verification' => [
        'subject' => ':platform — Confirm your email',
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

    // Landing contact form message → the team's inbox. The subject still comes
    // from contact.mail.subject_line; only the body parts live here.
    'contact_message' => [
        'preheader' => 'New message from :name (:subject).',
        'heading' => 'New message from the contact form',
        'message_label' => 'Message',
        'reply_hint' => 'Replying to this email answers the sender directly.',
    ],

    // Email preview screen (/mail-preview) — development only.
    'preview' => [
        'title' => 'Email preview',
        'subtitle' => 'Every transactional email in the kit with sample data, in the three languages and both themes. A development tool: in production this route returns 404.',
        'list_heading' => 'Emails',
        'language' => 'Language',
        'scheme' => 'Theme',
        'subject' => 'Subject',
        'plain_text' => 'Plain text version',
        'open_html' => 'Open the HTML',
        'open_text' => 'View plain text',
        'mailpit_hint' => 'To check how the email actually arrives (headers, multipart, attachments), trigger the flow and open Mailpit at http://localhost:18025.',
        'emails' => [
            'email-verification' => 'Email confirmation',
            'verification-code' => 'Verification code',
            'login-code' => 'Sign-in code (login)',
            'password-reset' => 'Password reset',
            'api-key-inactivity' => 'Inactive API key',
            'contact-message' => 'Contact form',
        ],
        'locales' => ['pt_BR' => 'Português', 'en' => 'English', 'es' => 'Español'],
        'schemes' => ['light' => 'Light', 'dark' => 'Dark'],
    ],

];
