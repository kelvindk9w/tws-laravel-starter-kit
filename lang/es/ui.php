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
        'label' => 'Tema',
        'system' => 'Sistema',
        'light' => 'Claro',
        'dark' => 'Oscuro',
    ],

    // Seletor de arquivo do kit (<x-file-input>): o chrome nativo do
    // <input type="file"> é traduzido pelo SISTEMA OPERACIONAL, não por nós.
    'file' => [
        'choose' => 'Elegir archivo',
        'empty' => 'Ningún archivo seleccionado',
    ],

    'chart' => [
        'empty_title' => 'Sin datos en el período',
    ],

    'password' => [
        'show' => 'Mostrar contraseña',
        'hide' => 'Ocultar contraseña',
    ],

    // Resumen de errores de validación (<x-form-errors>).
    'form_errors' => [
        'title' => 'Corrige los campos destacados',
    ],

    'footer' => [
        'operated_by' => 'Operado por :platform',
        'support' => 'Soporte',
    ],

];
