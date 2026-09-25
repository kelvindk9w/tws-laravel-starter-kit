<?php

declare(strict_types=1);

// Authentication domain messages (en) — the ones returned by the rules of the
// twstec/kit-auth package (refusals, notices, password policy). SCREEN texts
// (titles, labels, buttons) belong to the front. The application wins: the
// same key in its lang/ takes precedence over this one.

return [

    'failed' => 'These credentials do not match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'account_inactive' => 'This account is not active. Please contact support.',
    'registered' => 'Account created successfully. Welcome!',
    'logged_out' => 'Signed out successfully.',
    'password_policy' => [
        'min' => 'at least :min characters',
        'with' => ':min, with :rules',
        'separator' => ', ',
        'letters' => 'at least one letter',
        'mixed_case' => 'upper and lower case',
        'numbers' => 'at least one number',
        'symbols' => 'at least one symbol',
    ],
    'email_verification' => [
        'sent' => 'We sent a new confirmation link to your email.',
        'registered' => 'Account created. Confirm your email to unlock the dashboard.',
        'cooldown' => 'Please wait :seconds seconds before requesting another email.',
        'verified' => 'Email confirmed. Welcome!',
        'invalid_link' => 'This confirmation link is invalid or has expired. Request a new one below.',
        'not_verified' => 'Confirm your email to continue.',
        'wrong_account' => 'This link belongs to another account. Sign out and sign in with the account that received the email.',
    ],
    'transaction_password' => [
        'invalid' => 'The provided transaction password is incorrect.',
        'current_invalid' => 'The current transaction password is incorrect.',
        'same_as_login' => 'The transaction password must be different from the login password.',
        'saved' => 'Transaction password saved successfully.',
    ],
    'verification_code' => [
        'sent' => 'We sent a verification code to your email.',
        'invalid' => 'The provided code is invalid.',
        'expired' => 'The code has expired or does not exist. Request a new one.',
        'resend_cooldown' => 'Please wait :seconds seconds before requesting a new code.',
    ],
    'two_factor' => [
        'code_label' => 'Verification code',
        'invalid' => 'Incorrect code. Check the latest email you received.',
        'expired' => 'This code has expired or was already used. Request a new one below.',
        'resend_cooldown' => 'Please wait :seconds seconds before requesting another code.',
        'resent' => 'We sent a new code to your email.',
        'cancelled' => 'Sign-in cancelled. Nothing was authenticated.',
        'challenge_expired' => 'The verification expired. Sign in again with your password.',
        'locked' => 'Too many incorrect codes. For your security, wait :minutes minute(s) and sign in again.',
        'unavailable' => 'Two-step verification is not available on this installation.',
        'account_protected' => 'Not available on this account: it is protected, and two-step verification cannot be turned on for it.',
        // Old name (up to 2.x), same text: removed in 3.0.
        'demo_blocked' => 'Not available on this account: it is protected, and two-step verification cannot be turned on for it.',
        'requires_transaction_password' => 'Set your transaction password first: turning two-step verification on and off are sensitive actions.',
    ],
    'sensitive_action' => [
        'token_issued' => 'Sensitive action authorized. Use the token immediately — it is single-use.',
        'invalid_token' => 'Missing, invalid or expired sensitive action token. Please confirm the action again.',
    ],

];
