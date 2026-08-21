<?php

declare(strict_types=1);

// UI strings (en). Every user-facing string goes through __() — ADR-007.

return [

    'locale' => [
        'label' => 'Language',
        'names' => [
            'pt_BR' => 'Português (Brasil)',
            'en' => 'English',
            'es' => 'Español',
        ],
    ],

    'theme' => [
        'toggle' => 'Theme: switch between system, light and dark',
        'system' => 'System',
        'light' => 'Light',
        'dark' => 'Dark',
    ],

    'password' => [
        'show' => 'Show password',
        'hide' => 'Hide password',
    ],

    // Validation error summary (<x-form-errors>).
    'form_errors' => [
        'title' => 'Review the highlighted fields',
    ],

    'footer' => [
        'operated_by' => 'Operated by :platform',
        'support' => 'Support',
    ],

];
