<?php

declare(strict_types=1);

// Authentication (en): the messages returned by the rules of the
// twstec/kit-auth package (refusals, notices, password policy) and the texts
// of the starters' sign-in, sign-up and verification screens. The application
// wins: the same key in its lang/ takes precedence over this one (see
// docs/personalizacao.md).

return [

    'failed' => 'These credentials do not match our records.',

    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'account_inactive' => 'This account is not active. Please contact support.',

    'registered' => 'Account created successfully. Welcome!',

    'logged_out' => 'Signed out successfully.',

    'session_ended' => 'Your session was ended because the password or two-step verification of the account changed. Please sign in again.',

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

        // Email verification at sign-up (Twstec\Kit\Auth\Support\EmailVerification).
        'title' => 'Confirm your email',
        'intro' => 'We sent a confirmation link to :email. Open the email and click the link to unlock the dashboard.',
        'hint' => 'Didn\'t get it? Check your spam folder or request a new one.',
        'resend' => 'Resend email',
    ],

    'password_confirmation' => [
        'locked' => 'Too many incorrect password attempts. For your security, wait :minutes minute(s) and try again.',
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
        'setup_required' => 'Two-step verification is required on this installation. Set it up to continue.',
        'required_cannot_disable' => 'Two-step verification is required on this installation and cannot be turned off.',
        'setup_done' => 'Two-step verification is on. From your next sign-in, we will ask for the code sent to your email.',

        // Two-step verification at LOGIN (TwoFactorLogin): code screen, flow
        // messages and the refusals to turn it on/off.
        'title' => 'Two-step verification',
        'intro' => 'We sent a 6-digit code to :email. Enter it below to finish signing in — it is valid for :minutes minutes.',
        'submit' => 'Confirm and sign in',
        'resend' => 'Send another code',
        'resend_hint' => 'Didn\'t get it? Check your spam folder. A new code invalidates the previous one.',
        'cancel' => 'Back to sign in',
        'enabled' => 'Two-step verification is on. From your next sign-in, we will ask for the code sent to your email.',
        'disabled' => 'Two-step verification is off. Sign-in asks for your password only again.',
    ],

    'sensitive_action' => [
        'token_issued' => 'Sensitive action authorized. Use the token immediately — it is single-use.',
        'invalid_token' => 'Missing, invalid or expired sensitive action token. Please confirm the action again.',
    ],

    'password' => 'The provided password is incorrect.',

    // Setting up REQUIRED two-step verification (AUTH_TWO_FACTOR_REQUIRED).
    'two_factor_setup' => [
        'title' => 'Set up two-step verification',
        'intro' => 'This installation requires two-step verification. Once set up, every sign-in asks for your password and a code sent to :email.',
        'grace' => 'You can postpone until :date. After that, the panel only opens with two-step verification set up.',
        'date_format' => 'm/d/Y',
        'transaction_password_heading' => 'First, set your transaction password',
        'transaction_password_hint' => 'Turning verification on is a sensitive action: it is confirmed with your transaction password (different from your sign-in password) and a code by email.',
        'confirm_heading' => 'Confirm with your transaction password',
        'confirm_hint' => 'We will send a 6-digit code to your email.',
        'send_code' => 'Send code',
        'code_intro' => 'We sent a 6-digit code to :email. Enter it below — it is valid for :minutes minutes.',
        'submit' => 'Turn on and continue',
        'resend_hint' => 'Didn\'t get it? Check your spam folder or request another code with your transaction password.',
        'later' => 'Set up later',
    ],

    // UI strings (authentication forms/screens).
    'ui' => [
        'login_title' => 'Sign in',
        'login_submit' => 'Sign in',
        'login_link' => 'Already have an account? Sign in',
        'register_title' => 'Create account',
        'register_submit' => 'Create account',
        'register_link' => 'Create account',
        'name' => 'Full name',
        'email' => 'Email',
        'password' => 'Password',
        'new_password' => 'New password',
        'password_confirmation' => 'Confirm password',
        'remember_me' => 'Stay signed in',
        'forgot_password' => 'Forgot my password',
        'forgot_title' => 'Recover password',
        'forgot_subtitle' => 'Enter your email to receive the reset link.',
        'forgot_submit' => 'Send reset link',
        'reset_title' => 'Reset password',
        'reset_submit' => 'Reset password',
        'logout' => 'Sign out',
        'save' => 'Save',
        'transaction_password_title' => 'Transaction password',
        'transaction_password_subtitle' => 'Used to authorize sensitive actions (account deletion, API keys). It must be different from the login password.',
        'current_transaction_password' => 'Current transaction password',
        'new_transaction_password' => 'New transaction password',
        'dashboard_title' => 'Dashboard',
        'dashboard_greeting' => 'Hello, :name',
        'dashboard_code' => 'Your user code',
    ],

];
