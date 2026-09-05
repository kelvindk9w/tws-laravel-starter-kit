<?php

declare(strict_types=1);

// Public landing page (/) strings — en (ADR-007). NEVER hardcoded text in views.

return [

    'nav' => [
        'features' => 'Features',
        'hours' => 'Time saved',
        'stack' => 'Stack',
        'components' => 'Components',
        'login' => 'Sign in',
        'demo' => 'Try the demo',
        'register' => 'Create account',
        'dashboard' => 'Go to dashboard',
        'menu' => 'Menu',
        'open_menu' => 'Open navigation menu',
    ],

    'hero' => [
        'title' => 'Your Laravel SaaS in production in days, not months',
        'subtitle' => 'Authentication with 2FA, API keys with rotation, multitenancy, Livewire dashboard, Filament admin, secure uploads, backups and tests — all done and audited. You only build what makes your product unique.',
        'cta_components' => 'Explore components',
        'cta_demo' => 'Try the demo',
        'cta_admin_demo' => 'See the admin demo',
        'cta_register' => 'Create account',
        'screenshot_alt' => 'Real screenshot of the kit dashboard',
        'mockup_title' => 'Dashboard',
        'mockup_row_1' => 'Active API keys',
        'mockup_row_2' => 'Projects',
        'mockup_row_3' => 'Audited requests',
    ],

    'stack' => [
        'heading' => 'A current stack, tested in production',
        'items' => ['Laravel 13', 'PHP 8.4', 'PostgreSQL 18', 'Redis 8', 'Livewire 4', 'Filament 5', 'Tailwind 4', 'Horizon', 'Pest', 'Docker'],
        'dev_note' => 'Complete dev environment in compose: Mailpit (email inbox), Horizon (queues) and scheduler — nothing to install beyond Docker.',
    ],

    'hours' => [
        'heading' => 'Hours you won’t have to spend',
        'subtitle' => 'A conservative estimate of what already ships implemented, tested and documented.',
        'items' => [
            ['task' => 'Complete authentication with email 2FA and attempt lockout', 'hours' => 40],
            ['task' => 'Transaction password + sensitive action confirmation', 'hours' => 24],
            ['task' => 'API keys with scopes, rotation and inactivity expiration', 'hours' => 40],
            ['task' => 'Project-based multitenancy with data isolation', 'hours' => 24],
            ['task' => 'User dashboard (Livewire) + super admin (Filament)', 'hours' => 56],
            ['task' => 'Secure uploads with image re-encoding and signed URLs', 'hours' => 24],
            ['task' => 'Request logging, auditing and LGPD redaction', 'hours' => 16],
            ['task' => 'Encrypted backup to R2 with cross-validation', 'hours' => 16],
            ['task' => 'Queues with Horizon, CSP and rate limiting', 'hours' => 16],
            ['task' => 'Pest test suite + Playwright E2E', 'hours' => 24],
        ],
        'total_label' => 'Total saved',
        'total_value' => ':hours hours',
        'total_unit' => 'hours of work already done',
        'total_caption' => 'A conservative estimate of what already ships implemented, tested and documented — itemised below.',
    ],

    'features' => [
        'heading' => 'Everything a serious SaaS needs',
        'subtitle' => 'Not a toy boilerplate: every feature follows a security checklist and has tests.',
        'items' => [
            ['icon' => 'shield-check', 'title' => 'Authentication + 2FA', 'description' => 'Registration, sign-in, password reset and email code verification, with attempt lockout and regenerated sessions.'],
            ['icon' => 'lock-closed', 'title' => 'Transaction password', 'description' => 'A second secret (separate hash) to confirm sensitive actions, with single-use, short-lived tokens.'],
            ['icon' => 'key', 'title' => 'API keys with rotation', 'description' => 'pk_/sk_ keys with granular scopes, rotation grace period and inactivity deactivation.'],
            ['icon' => 'building-office', 'title' => 'Project-based multitenancy', 'description' => 'Each user organizes resources into projects with isolation enforced by global scopes and tests.'],
            ['icon' => 'squares-2x2', 'title' => 'Livewire dashboard', 'description' => 'Dashboard, API keys, projects, notifications and profile in Livewire 4 — direct UI, modals instead of navigation.'],
            ['icon' => 'cog-6-tooth', 'title' => 'Filament super admin', 'description' => 'The /admin panel in Filament 5 restricted to administrators, with an IP allowlist for production.'],
            ['icon' => 'arrow-up-tray', 'title' => 'Secure uploads', 'description' => 'Validation by real file signature, GD image re-encoding and short-lived signed URLs.'],
            ['icon' => 'clipboard-document-list', 'title' => 'Auditing and logs', 'description' => 'Request logging to database and file with sensitive data redaction (LGPD) and configurable retention.'],
            ['icon' => 'archive-box', 'title' => 'Backups and queues', 'description' => 'Encrypted PostgreSQL dump to R2 with cross-validation webhook, and Horizon for queues.'],
            ['icon' => 'beaker', 'title' => 'Real tests', 'description' => 'Pest feature coverage across every module + Playwright E2E — content validation, not just status codes.'],
            ['icon' => 'language', 'title' => 'Native i18n', 'description' => 'Every string goes through language files (lang/), never hardcoded text in views — multi-language ready out of the box.'],
            ['icon' => 'server-stack', 'title' => 'Self-contained Docker', 'description' => 'Only Docker on your machine: compose brings up app, database, Redis, queues and scheduler — including the production stack.'],
        ],
    ],

    'cta' => [
        'heading' => 'Ready to build?',
        'subtitle' => 'Create your account and explore the dashboard, or dive into the code: every decision is documented in ADRs.',
        'repo' => 'See the code on the repository',
        'register' => 'Create account',
        'demo' => 'Try the demo',
        'login' => 'Sign in',
    ],

    'footer' => [
        'tagline' => 'Laravel starter kit for SaaS — a ready structural base to build on.',
        'links_heading' => 'Shortcuts',
        'showcase' => 'Components',
        'demo' => 'Demo sign-in',
        'contact' => 'Contact',
        'api_status' => 'API status',
        'rights' => '© :year :company — All rights reserved',
        'developed_by' => 'Developed by',
    ],

];
