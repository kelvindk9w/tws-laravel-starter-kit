<?php

declare(strict_types=1);

// Strings of the official landing (route /) — English (ADR-007).
// Numbers (clones, tests, hours) live in config/landing.php, not here.
//
// `nav.*` and the institutional half of `footer.*` are also used by the product
// header and footer (<x-site-header>, <x-site-footer>, Navigation) — they are
// not exclusive to this page.

return [

    'meta' => [
        'title' => "The base your AI doesn't have to write",
        'description' => 'Auth, 2FA, API keys, redacted logs, uploads, user panel and super admin already built and tested in a Laravel starter kit. Clone the base and spend your tokens on what is only yours.',
    ],

    'a11y' => [
        'skip' => 'Skip to content',
    ],

    'nav' => [
        'features' => 'Features',
        'hours' => 'Time saved',
        'stack' => 'Stack',
        'components' => 'Components',
        'login' => 'Sign in',
        'register' => 'Create account',
    ],

    'hero' => [
        'proof_clones' => 'developers have cloned it',
        'proof_tests' => 'green tests on every commit',
        'proof_avatar_alt' => 'Profile picture of someone who cloned the kit',
        'title_line_1' => 'The base your AI',
        'title_line_2' => "doesn't have to write",
        'subtitle' => 'Auth, 2FA, API keys, redacted logs, uploads, panel and admin already built and tested. Clone it and spend your tokens on what is only yours.',
        'cta_primary' => 'Clone it',
        'cta_demo' => 'See the demo',
        'cta_admin_demo' => 'See the admin demo',
        'note' => 'free, MIT',
        'note_secondary' => 'no card',
        'screens_heading' => 'Real screens from the kit',
        'screens' => [
            'dashboard' => ['label' => 'Panel', 'alt' => 'The kit user panel: API key metrics, a requests chart and the latest API calls'],
            'admin' => ['label' => 'Super admin', 'alt' => 'Filament super admin: users, audited requests and form submissions'],
            'ui' => ['label' => 'Components', 'alt' => 'The /ui showcase: living documentation of the kit components'],
            'login' => ['label' => 'Sign in', 'alt' => 'The kit sign-in screen'],
        ],
        'chip_tenancy' => 'Multitenancy',
        'chip_2fa' => '2FA',
        'chip_api_keys' => 'Scoped API keys',
        'chip_lgpd' => 'GDPR/LGPD',
    ],

    'components' => [
        'eyebrow' => 'Components',
        'title' => 'You are not drawing that table again',
        'subtitle' => 'The code you write and the screen your customer sees are the same thing. Drag the line and check.',
        'code_label' => 'Code',
        'screen_label' => 'Screen',
        'drag_hint' => 'Drag',
        'slider_label' => 'Reveal the code or the rendered screen',
        'screen_alt' => 'The kit table rendered in the /ui showcase, with status badges and per-row actions',
        'items' => [
            [
                'title' => 'The table becomes cards',
                'text' => 'Below sm every row changes shape and carries its own label. No clipped column, no hidden sideways scroll.',
            ],
            [
                'title' => 'One header for everything',
                'text' => 'Landing, showcase, auth screens and panel share one skeleton. Signing in must never feel like switching products.',
            ],
            [
                'title' => 'The identity in one file',
                'text' => 'Colors, type, surfaces, radii and motion live in theme.css. Rebranding is editing one file and the .env.',
            ],
        ],
    ],

    'how' => [
        'title' => 'From clone to first screen in three commands',
        'subtitle' => 'The afternoon lost setting up an environment ends at Docker: only it on your machine, no PHP, Composer or Node.',
        'steps' => [
            [
                'cursor' => 'clone',
                'title' => 'Clone',
                'text' => 'One repository, an MIT license and no paid dependency hiding inside.',
                'command' => 'git clone <repo> my-project',
            ],
            [
                'cursor' => 'fill the .env',
                'title' => 'Fill the .env',
                'text' => 'Name, logo, color, locales and platform e-mails. No institutional copy buried in the code.',
                'command' => 'cp .env.example .env',
            ],
            [
                'cursor' => 'boot it',
                'title' => 'Boot it',
                'text' => 'Postgres, Redis, queues, scheduler and a mailbox come up together, owned by your user.',
                'command' => 'docker compose up -d --build',
            ],
        ],
    ],

    'security' => [
        'title' => 'Security out of the box',
        'subtitle' => 'The part nobody ever has time for — and nobody forgives when it is missing — ships implemented, tested and switched on.',
        'items' => [
            ['title' => 'E-mail 2FA', 'text' => 'Single-use code, short expiry and attempt lockout — on sign-in and on sensitive actions.'],
            ['title' => 'Transaction password', 'text' => 'A second secret, hashed apart from the sign-in password, for what cannot be undone.'],
            ['title' => 'Scoped API keys', 'text' => 'Public prefix, hash in the database, rotation, expiry on inactivity and deny by default.'],
            ['title' => 'Logs with redaction', 'text' => 'Every request audited end to end, with sensitive fields masked before they are stored.'],
            ['title' => 'Re-encoded uploads', 'text' => 'Magic bytes, a pixel ceiling and an image re-encode: the file that goes in is not the file that stays.'],
            ['title' => 'Encrypted backup', 'text' => 'Database and files to R2, password protected, cross-validated and alerting when a backup fails.'],
        ],
    ],

    // "Production ready": what the base ships beyond security — the two
    // features inherited from the previous landing when it was retired.
    'ready' => [
        'title' => 'Production ready',
        'items' => [
            ['title' => 'Mailpit and e-mails ready', 'text' => 'A development mail server already in the compose file, one transactional template in three languages, automatic plain text and a preview screen.'],
            ['title' => 'Built from components', 'text' => 'Panel, admin and e-mails assembled from the same reusable components — documented and browsable in the /ui showcase.'],
        ],
    ],

    // The number that matters to anyone building with AI. It comes from
    // config('landing.hours_saved'); zero hides the whole line.
    'hours' => [
        'label' => 'hours of work nobody has to generate again',
        'caption' => 'A conservative sum of what already ships implemented, tested and documented — auth, 2FA, API keys, multitenancy, panel, admin, uploads, logs, backup and the test suite.',
    ],

    'contact' => [
        'anchor_label' => 'Contact',
    ],

    'footer' => [
        'title' => 'Start on your own product',
        'subtitle' => 'Day 1 of your project ships with the security of day 300.',
        'cta' => 'Clone the repository',
        'tech_heading' => 'The stack already assembled',
        'tech' => [
            'laravel' => 'Laravel',
            'php' => 'PHP',
            'postgres' => 'PostgreSQL',
            'redis' => 'Redis',
            'docker' => 'Docker',
            'livewire' => 'Livewire',
            'filament' => 'Filament',
            'tailwind' => 'Tailwind',
        ],

        // Institutional footer of the kit (<x-site-footer>) — present on every
        // screen, not only here.
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
