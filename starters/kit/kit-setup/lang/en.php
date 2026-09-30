<?php

declare(strict_types=1);

// Texts of the single command (composer create-project twstec/kit) — en.

return [
    'intro' => 'TWS Laravel Starter Kit — creating the project in :dir',
    'always' => 'Always included: Foundation (twstec/kit-foundation — security, audit, locale, e-mail) and Authentication (twstec/kit-auth — login, sign-up, e-mail verification, second factor).',
    'aborted' => 'Nothing was installed. Delete the :dir folder and run the command again whenever you want.',
    'confirm' => 'Create the project like this?',

    'name' => [
        'label' => 'Project name',
        'hint' => 'Lowercase letters, digits and hyphen. It becomes the address http://<name>.localhost, the container names and the database name.',
    ],

    'slot' => [
        'label' => 'Project number (the ports)',
        'hint' => 'Every port ends with it: site 808N, e-mails 802N, Vite 803N, database 804N. From 0 to 9; then 10 to 99 (the next hundred: 818N…).',
        'intro' => 'Each project uses a number, and its ports end with that number (2 is the site on 8082, the e-mails on 8022…).',
        'used' => ':slot is already used by :owners',
        'next_hundred' => 'From 0 to 9 there is no number with all four ports free: the suggestion is :slot, in the next hundred (same pattern, 818N, 812N…).',
        'invalid' => 'Type a number from 0 to 99.',
    ],

    'dev' => [
        'docker_unavailable' => 'Docker did not respond from here: the names of other projects cannot be checked, and the ports only by what can be opened on this machine.',
        'other_program' => 'another program',
    ],

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
        'summary' => "Project: :name — :site (e-mails: :mail)\nInterface: :stack\nOptional modules: :modules",
        'from_environment' => 'Choice (no questions): project :name, number :slot; :stack; optional modules: :modules.',
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
        'in_variable' => 'In :variable:',
        'name_format' => 'the name ":name" does not work: use lowercase letters, digits and hyphen, starting with a letter (suggestion: :suggestion).',
        'name_length' => 'the name ":name" must have 2 to 40 characters (suggestion: :suggestion).',
        'name_taken' => 'there is already a Docker project called :name on this machine (containers or volumes, even stopped). Use another name — suggestion: :suggestion.',
        'slot_invalid' => 'invalid project number: :slot. Use 0 to 99 (:variable).',
        'slot_busy' => 'number :slot is in use — :occupants.',
        'slot_suggestion' => 'The first number with all four ports free is :suggestion.',
        'no_free_slot' => 'No number from 0 to 99 has all four ports free. Stop projects you are not using (docker compose stop, in each project folder) and run again.',
        'expose_invalid' => 'Invalid value in TWS_KIT_EXPOSE_DB: :value. Use 1 (publish the database) or 0.',
    ],

    'steps' => [
        'download' => 'Downloading the :package starter (:constraint)',
        'replace' => 'Assembling the project with the starter files',
        'modules' => 'Modules in composer.json: :modules',
        'env' => 'Preparing .env (the starter post-root-package-install)',
        'install' => 'Installing the dependencies (composer update)',
        'installer' => 'Keys, development Docker and database by the starter installer (tws:install, without asking again)',
    ],

    'failures' => [
        'download' => 'Could not download the :package starter (see the Composer message above). Nothing was installed: delete the :dir folder and run the command again.',
        'unexpected' => 'The downloaded package is not the expected starter (:package). Nothing was installed: delete the :dir folder and run the command again.',
        'replace' => 'Could not assemble the project in :dir (:reason). Delete the folder and run the command again.',
        'incomplete' => 'The project in :dir is incomplete: the ":step" step failed (see the output above).',
        'finish' => 'To finish without starting over, after fixing the cause:',
        'finish_after_extensions' => 'With the extensions installed, to finish without starting over:',
        'restart' => 'Or delete the :dir folder and run the command again.',
        'leftover' => 'Could not delete :path (file in use?). It belongs to the installer, not to the project: delete it.',
    ],

    // Missing PHP extensions (the `composer update` refused).
    'extensions' => [
        'missing' => 'PHP extensions missing on this machine: :extensions. There are two ways out:',
        'install' => '1) Install the extensions in this machine PHP (check with `php -m`):',
        'windows' => '   • Windows: in php.ini (`php --ini` shows where it is), remove the ";" at the start of the lines :lines (the PHP from windows.php.net already has the files).',
        'linux' => '   • Ubuntu/Debian: sudo apt install :packages',
        'mac' => '   • macOS (Homebrew): the PHP from `brew install php` already has these extensions; check which PHP the terminal uses (`which php`).',
        'docker' => '2) Or use the Docker-only way, which has everything: delete this folder, download twstec-kit (Code → Download ZIP) and, inside its folder, run `docker compose run --rm instalar`.',
        'windows_horizon' => 'Windows: Horizon (the queue dashboard) needs the pcntl and posix extensions, which the Windows PHP does not have — they were ignored during installation, and only Horizon is left out. Everything else runs normally; to process the queue, use `php artisan queue:work`. For the next Composer commands in this project, set `$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"` first. The Docker-only way (docker compose run --rm instalar) runs everything, Horizon included.',
    ],

    'database' => [
        'ready' => 'Database ready: migrations applied.',
        'unreachable' => 'The .env database (DB_CONNECTION=:connection, DB_HOST=:host) did not respond: the migrations were left for later.',
        'how' => 'Adjust the DB_* variables in .env — the kit development docker-compose.yml uses PostgreSQL; for SQLite, DB_CONNECTION=sqlite and delete the DB_DATABASE line (the database/database.sqlite file already exists) — and run:',
        'docker' => 'The project database runs in Docker: the first `docker compose up -d` creates the database and runs the migrations by itself.',
    ],

    'done' => [
        'heading' => 'Project ready in :dir',
        'stack' => 'Interface: :stack',
        'modules' => 'Modules: :modules',
        'next' => 'Next steps:',
        'docker' => 'Development Docker: project :name, number :slot (the ports end with :slot; everything in .env).',
        'site' => 'Site: :url',
        'mail' => 'Sent e-mails (Mailpit): :url',
        'database_exposed' => 'Database: published on port :port of this machine (127.0.0.1), with the .env password.',
        'database_internal' => 'Database and Redis: only inside Docker (to publish the database on port :port, COMPOSE_PROFILES=db-port in .env).',
        'open' => 'Then open :url in the browser (the first start takes a few minutes: it builds the images).',
    ],

    // Inside the installer container (docker compose run --rm instalar): the
    // folder is the ZIP one, and the way is Docker.
    'docker' => [
        'intro' => 'TWS Laravel Starter Kit — creating the project in this folder',
        'aborted' => 'Nothing was installed. Run `docker compose run --rm instalar` again whenever you want.',
        'errors' => [
            'nothing_installed' => 'Nothing was installed. Fix the variables and run `docker compose run --rm instalar` again.',
        ],
        'failures' => [
            'download' => 'Could not download the :package starter (see the Composer message above). Nothing was installed: check the internet connection and run `docker compose run --rm instalar` again.',
            'unexpected' => 'The downloaded package is not the expected starter (:package). Nothing was installed: run `docker compose run --rm instalar` again.',
            'replace' => 'Could not assemble the project in this folder (:reason). Download the twstec-kit ZIP again, into a new folder, and run the installer.',
            'incomplete' => 'The project in this folder is incomplete: the ":step" step failed (see the output above).',
            'restart' => 'Download the twstec-kit ZIP again, into a new folder, and run `docker compose run --rm instalar` (delete this folder).',
        ],
        'done' => [
            'heading' => 'Project ready in this folder',
        ],
    ],
];
