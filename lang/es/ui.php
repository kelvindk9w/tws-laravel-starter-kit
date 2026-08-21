<?php

declare(strict_types=1);

// Cadenas de interfaz (es). Toda cadena visible pasa por __() — ADR-007.

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
        'toggle' => 'Tema: alterna entre sistema, claro y oscuro',
        'system' => 'Sistema',
        'light' => 'Claro',
        'dark' => 'Oscuro',
    ],

    'password' => [
        'show' => 'Mostrar contraseña',
        'hide' => 'Ocultar contraseña',
    ],

    'footer' => [
        'operated_by' => 'Operado por :platform',
        'support' => 'Soporte',
    ],

];
