<?php

declare(strict_types=1);

// Authentication strings (en). Every UI string goes through __() — ADR-007.

return [

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    // Login password policy hint, built by PasswordPolicy::hint() from the
    // ACTIVE rules only (config/auth.php → password_rules).
    'password_policy' => [
        'min' => 'at least :min characters',
        'with' => ':min, with :rules',
        'separator' => ', ',
        'letters' => 'at least one letter',
        'mixed_case' => 'upper and lower case',
        'numbers' => 'at least one number',
        'symbols' => 'at least one symbol',
    ],

    'account_inactive' => 'This account is not active. Please contact support.',
    'registered' => 'Account created successfully. Welcome!',
    'logged_out' => 'Signed out successfully.',

    // Transaction password (ADR-006 — separate from the login password).
    'transaction_password' => [
        'invalid' => 'The provided transaction password is incorrect.',
        'current_invalid' => 'The current transaction password is incorrect.',
        'same_as_login' => 'The transaction password must be different from the login password.',
        'saved' => 'Transaction password saved successfully.',
    ],

    // Verification code (email 2FA).
    'verification_code' => [
        'sent' => 'We sent a verification code to your email.',
        'invalid' => 'The provided code is invalid.',
        'expired' => 'The code has expired or does not exist. Request a new one.',
        'resend_cooldown' => 'Please wait :seconds seconds before requesting a new code.',
    ],

    // Sensitive action token (single use, short-lived — ADR-006/010).
    'sensitive_action' => [
        'token_issued' => 'Sensitive action authorized. Use the token immediately — it is single-use.',
        'invalid_token' => 'Missing, invalid or expired sensitive action token. Please confirm the action again.',
    ],

    // UI strings (authentication forms/screens).
    // Demo account hardening (DemoAccountGuard): messages for anything that
    // tries to touch them outside the super admin UI — tinker, artisan
    // command, job. See the README, "Demo accounts are untouchable".
    'demo_account' => [
        'update_blocked' => 'Protected demo account: ":email" does not accept changes to :fields. Name, photo, language and theme remain editable.',
        'delete_blocked' => 'Protected demo account: ":email" cannot be deleted.',
    ],

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
        'transaction_password_subtitle' => 'Used to authorize sensitive actions (withdrawals, API keys). It must be different from the login password.',
        'current_transaction_password' => 'Current transaction password',
        'new_transaction_password' => 'New transaction password',
        'dashboard_title' => 'Dashboard',
        'dashboard_greeting' => 'Hello, :name',
        'dashboard_code' => 'Your user code',

        // Demo login: only when config('ui.demo_login.enabled') — local/dev.
        'demo_notice' => 'Demo environment: the credentials below are already filled in, just sign in.',
        'demo_credentials' => 'Demo user',
    ],

];
