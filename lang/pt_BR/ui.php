<?php

declare(strict_types=1);

// Strings de interface (UI) — pt-BR. Toda string exibida ao usuário passa
// por __() apontando para estes arquivos (ADR-007). NUNCA texto fixo em views.

return [

    'locale' => [
        'label' => 'Idioma',
        'names' => [
            'pt_BR' => 'Português (Brasil)',
            'en' => 'English',
            'es' => 'Español',
        ],
    ],

    'theme' => [
        'toggle' => 'Tema: alterna entre sistema, claro e escuro',
        'system' => 'Sistema',
        'light' => 'Claro',
        'dark' => 'Escuro',
    ],

    'footer' => [
        'operated_by' => 'Operado por :platform',
        'support' => 'Suporte',
    ],

];
