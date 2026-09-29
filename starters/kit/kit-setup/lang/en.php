<?php

declare(strict_types=1);

// Texts of the single command (composer create-project twstec/kit) — en.

return [
    'intro' => 'TWS Laravel Starter Kit — creating the project in :dir',
    'always' => 'Always included: Foundation (twstec/kit-foundation — security, audit, locale, e-mail) and Authentication (twstec/kit-auth — login, sign-up, e-mail verification, second factor).',
    'aborted' => 'Nothing was installed. Delete the :dir folder and run the command again whenever you want.',
    'confirm' => 'Create the project like this?',

    'stack' => [
        'label' => 'Which interface?',
        'hint' => 'The user panel. The packages, the rules and /admin are the same in both.',
        'livewire' => 'Livewire — Livewire 4 + Blade (twstec/starter-livewire)',
        'react' => 'React — React 19 + Inertia + TypeScript + shadcn/ui (twstec/starter-react)',
    ],

    'modules' => [
        'label' => 'Which optional modules?',
        'hint' => 'Space toggles; Enter confirms. Uploads needs Accounts.',
        'none' => 'none (only foundation and authentication)',
        'foundation' => 'Foundation (twstec/kit-foundation)',
        'auth' => 'Authentication (twstec/kit-auth)',
        'accounts' => 'Accounts with members, API keys and projects (twstec/kit-accounts)',
        'uploads' => 'Secure uploads and profile photo (twstec/kit-uploads)',
        'admin' => '/admin panel with Filament (twstec/kit-admin)',
    ],

    'plan' => [
        'summary' => "Interface: :stack\nOptional modules: :modules",
        'from_environment' => 'Choice (no questions): :stack; optional modules: :modules.',
    ],

    'errors' => [
        'kit_config' => 'The twstec/kit composer.json has no extra.twstec-kit configuration (constraint and starters). Nothing was installed.',
        'unknown_stack' => 'Unknown interface in TWS_KIT_STACK: :stack. The options are: :stacks.',
        'unknown_module' => 'Unknown module in :variable: :module. The optional ones are: :modules.',
        'required_module' => 'The :module module is always included and cannot be left out (TWS_KIT_WITHOUT).',
        'with_and_without' => 'The :module module is in both TWS_KIT_WITH and TWS_KIT_WITHOUT.',
        'missing_dependency' => ':module needs :needs.',
        'missing_dependency_env' => 'Leave both out (TWS_KIT_WITHOUT=:both) or keep what is missing.',
        'nothing_installed' => 'Nothing was installed. Fix the variables, delete the :dir folder and run the command again.',
    ],

    'steps' => [
        'download' => 'Downloading the :package starter (:constraint)',
        'replace' => 'Assembling the project with the starter files',
        'modules' => 'Modules in composer.json: :modules',
        'env' => 'Preparing .env (the starter post-root-package-install)',
        'install' => 'Installing the dependencies (composer update)',
        'installer' => 'Keys and database by the starter installer (tws:install, without asking again)',
    ],

    'failures' => [
        'download' => 'Could not download the :package starter (see the Composer message above). Nothing was installed: delete the :dir folder and run the command again.',
        'unexpected' => 'The downloaded package is not the expected starter (:package). Nothing was installed: delete the :dir folder and run the command again.',
        'replace' => 'Could not assemble the project in :dir (:reason). Delete the folder and run the command again.',
        'incomplete' => 'The project in :dir is incomplete: the ":step" step failed (see the output above).',
        'finish' => 'To finish without starting over, after fixing the cause:',
        'restart' => 'Or delete the :dir folder and run the command again.',
        'leftover' => 'Could not delete :path (file in use?). It belongs to the installer, not to the project: delete it.',
    ],

    'database' => [
        'ready' => 'Database ready: migrations applied.',
        'unreachable' => 'The .env database (DB_CONNECTION=:connection, DB_HOST=:host) did not respond: the migrations were left for later.',
        'how' => 'Adjust the DB_* variables in .env — the kit development docker-compose.yml uses PostgreSQL; for SQLite, DB_CONNECTION=sqlite and delete the DB_DATABASE line (the database/database.sqlite file already exists) — and run:',
    ],

    'done' => [
        'heading' => 'Project ready in :dir',
        'stack' => 'Interface: :stack',
        'modules' => 'Modules: :modules',
        'next' => 'Next steps:',
    ],
];
