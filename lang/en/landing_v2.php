<?php

declare(strict_types=1);

// Strings for the "The Trace" landing (/v2) — English (ADR-007). Code samples
// do NOT live here: code is not a language, and duplicating it across three
// files would be three truths to maintain — they come from LandingV2Controller.

return [

    'meta' => [
        'title' => ':platform — everything that happens is recorded',
        'description' => 'A Laravel foundation with auditing, 2FA, API keys and tests already written. The trail starts on the first request.',
    ],

    'sound' => [
        'label' => 'Page sound',
        'on' => 'Sound on — click to mute',
        'off' => 'Sound off — click to hear the log pulse',
    ],

    'hero' => [
        'title' => 'Everything that happens is recorded.',
        'subtitle' => 'When the breach happens, you are the one who answers for it — and the trail is the only defense. A Laravel foundation with auditing, 2FA, API keys and tests already written.',
        'live_label' => 'Type anything',
        'live_hint' => 'A tax id, an email, a card number. The log line below is listening.',
        'live_placeholder' => 'my email is marina.duarte@example.com',
        'live_empty' => 'waiting for input…',
        'live_redacted' => 'redacted before it reaches the database',
        'live_clean' => 'nothing sensitive recognised',
        'clone' => 'Clone',
        'clone_copy' => 'Copy the command',
        'clone_copied' => 'Command copied',
        'demo' => 'Enter the demo',
        'scroll' => 'Scroll to read the trail',
        'canvas_alt' => 'Request log lines from the kit falling down the screen until they form the headline.',
    ],

    'trust' => [
        'heading' => 'Numbers from the repository',
        'plus' => ':value+',
        'tests' => 'Pest tests, green',
        'files' => 'test files',
        'license' => 'license — clone it, edit it, sell it',
        'version' => 'platform version',
        'build' => 'last frontend build',
    ],

    'mechanism' => [
        'pain' => 'Two weeks wiring up login, queues and Docker — and the product still does not exist.',
        'heading' => 'Three commands to your first log line.',
        'subtitle' => 'No proprietary CLI, no account, no license key. What runs on your machine is what runs in production.',
        'steps' => [
            [
                'title' => 'Clone',
                'description' => 'Your own copy, in your own Git, from day one.',
                'output' => 'Cloning into \'my-project\'... done.',
            ],
            [
                'title' => 'Boot',
                'description' => 'Docker is the only requirement. App, PostgreSQL, Redis, queues and scheduler come up together.',
                'output' => 'Container app  Started   Container queue  Started   Container scheduler  Started',
            ],
            [
                'title' => 'Prove',
                'description' => 'The whole suite passes before you write the first line of your product.',
                'output' => 'Tests:  :tests passed',
            ],
        ],
    ],

    'depth' => [
        'pain' => 'Every module you postpone for lack of time becomes next quarter\'s incident.',
        'heading' => 'What is already written.',
        'subtitle' => 'Six modules you will not have to build. Pick one: the real code opens here, ready to copy.',
        'hint' => 'Use the arrow keys to move through the modules.',
        'cells' => [
            'api_keys' => [
                'title' => 'API keys with scopes and rotation',
                'description' => 'A pk_/sk_ pair, granular scopes, rotation with a grace period and deactivation on inactivity.',
            ],
            'audit' => [
                'title' => 'Every request becomes a line',
                'description' => 'An append-only table with a correlation id, a redacted payload and a controlled lifecycle.',
            ],
            'sensitive' => [
                'title' => 'A sensitive action asks for two proofs',
                'description' => 'A transaction password (hashed apart from the login one) plus an emailed code, traded for a single-use token.',
            ],
            'uploads' => [
                'title' => 'Uploads that rewrite the file',
                'description' => 'Real file signature, GD re-encode (an embedded payload does not survive) and a short-lived signed URL.',
            ],
            'tenancy' => [
                'title' => 'Isolation per project',
                'description' => 'A global scope on the model and tests that try to leak from one tenant into another — and fail.',
            ],
            'i18n' => [
                'title' => 'Three languages, one key',
                'description' => 'Every string goes through lang/. A parity test fails the key that was left behind.',
            ],
        ],
        'copy' => 'Copy',
        'copied' => 'Copied',
    ],

    'thesis' => [
        'pain' => 'A customer password sitting in a log line is a leak nobody can undo.',
        'heading' => 'The trail keeps the fact, never the secret.',
        'subtitle' => 'Redaction runs when the request is received, before any processing. What you see below is the output of the class that runs in production — hover or tap to watch it work.',
        'hint_pointer' => 'Hover over the request',
        'hint_touch' => 'Tap the request',
        'redacting' => 'Redacting…',
        'redacted' => 'Redacted',
        'reset' => 'Show the original',
        'rules' => [
            'key' => 'sensitive key → replaced entirely',
            'email' => 'email → first letter and domain',
            'document' => 'tax id → first three and last two digits',
            'card' => 'card number → last four digits only',
            'none' => 'business data → preserved',
        ],
        'rules_heading' => 'Why each field changed',
        'chain_heading' => 'And the line never stalls halfway.',
        'chain_subtitle' => 'The log is born STARTED on arrival and only leaves that state through a controlled transition. A line still STARTED is an incident — that is how the kit finds what fell over.',
        'chain' => [
            'INICIADA' => 'Written before any business processing. If the request dies here, the evidence already exists.',
            'CONCLUIDA' => 'The response came back under 500. Duration and status land on the same line.',
            'ERRO' => 'Server error: the message is stored redacted, with the correlation id that ties the database log to the file log.',
            'BLOQUEADA' => 'Security validation refused the payload. The attempt stays recorded, inert, for the super admin showcase.',
        ],
    ],

    'proof' => [
        'pain' => 'Every starter kit landing promises a design system. Almost none let you touch it.',
        'heading' => 'Do not take our word: touch it.',
        'subtitle' => 'The controls below are the kit\'s real components, in your theme. Nothing here is an image.',
        'theme_title' => 'A theme, not an inversion',
        'theme_description' => 'Switch the theme: surfaces come from semantic tokens and keep the SAME elevation order in both. The whole page answers at once.',
        'components_title' => 'Real components',
        'twofa_title' => 'Sensitive action confirmation',
        'twofa_description' => 'A simulation of the real flow: we ask for the code, you type it, the kit answers. No email is sent from here.',
        'twofa_send' => 'Send code',
        'twofa_sending' => 'Sending…',
        'twofa_sent' => 'Code sent to the account email. In this demo, use :code.',
        'twofa_label' => 'Verification code',
        'twofa_confirm' => 'Confirm',
        'twofa_ok' => 'Action confirmed. Single-use token issued, valid for 5 minutes.',
        'twofa_error' => 'Invalid code. :attempts attempts left before lockout.',
        'twofa_reset' => 'Start over',
        'demo_field' => 'Project name',
        'demo_field_placeholder' => 'my-project',
        'demo_toggle' => 'Require transaction password',
        'demo_badge' => 'active',
        'demo_button' => 'Save project',
        'demo_saved' => 'Project saved.',
    ],

    'community' => [
        'pain' => 'A closed kit is a decision you can neither audit nor reverse.',
        'heading' => 'The repository is the documentation.',
        'subtitle' => 'A README with the architecture decisions written out: what was chosen, what was refused, and why.',
        'repo' => 'View on GitHub',
        'stars' => 'stars',
        'license_note' => ':license license',
        'version_note' => 'Version :version',
    ],

    'cta' => [
        'pain' => 'The first incident does not ask whether you had time to instrument.',
        'heading' => 'Start with the trail already written.',
        'subtitle' => 'Clone it, boot it and read the first line of your own log in under five minutes.',
        'button' => 'Clone the repository',
    ],

];
