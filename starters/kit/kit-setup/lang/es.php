<?php

declare(strict_types=1);

// Textos del comando único (composer create-project twstec/kit) — es.

return [
    'intro' => 'TWS Laravel Starter Kit — creando el proyecto en :dir',
    'always' => 'Siempre incluidos: Base (twstec/kit-foundation — seguridad, auditoría, idioma, correo) y Autenticación (twstec/kit-auth — inicio de sesión, registro, verificación de correo, segundo factor).',
    'aborted' => 'No se instaló nada. Borre la carpeta :dir y ejecute el comando de nuevo cuando quiera.',
    'confirm' => '¿Crear el proyecto así?',

    'stack' => [
        'label' => '¿Qué interfaz?',
        'hint' => 'El panel del usuario. Los paquetes, las reglas y el /admin son los mismos en las dos.',
        'livewire' => 'Livewire — Livewire 4 + Blade (twstec/starter-livewire)',
        'react' => 'React — React 19 + Inertia + TypeScript + shadcn/ui (twstec/starter-react)',
    ],

    'modules' => [
        'label' => '¿Qué módulos opcionales?',
        'hint' => 'Espacio marca y desmarca; Enter confirma. Uploads necesita Cuentas.',
        'none' => 'ninguno (solo la base y la autenticación)',
        'foundation' => 'Base (twstec/kit-foundation)',
        'auth' => 'Autenticación (twstec/kit-auth)',
        'accounts' => 'Cuentas con miembros, claves de API y proyectos (twstec/kit-accounts)',
        'uploads' => 'Uploads seguros y foto de perfil (twstec/kit-uploads)',
        'admin' => 'Panel /admin con Filament (twstec/kit-admin)',
    ],

    'plan' => [
        'summary' => "Interfaz: :stack\nMódulos opcionales: :modules",
        'from_environment' => 'Elección (sin preguntas): :stack; módulos opcionales: :modules.',
    ],

    'errors' => [
        'kit_config' => 'El composer.json de twstec/kit no tiene la configuración extra.twstec-kit (constraint y starters). No se instaló nada.',
        'unknown_stack' => 'Interfaz desconocida en TWS_KIT_STACK: :stack. Las opciones son: :stacks.',
        'unknown_module' => 'Módulo desconocido en :variable: :module. Los opcionales son: :modules.',
        'required_module' => 'El módulo :module siempre se incluye y no puede quedar fuera (TWS_KIT_WITHOUT).',
        'with_and_without' => 'El módulo :module está en TWS_KIT_WITH y en TWS_KIT_WITHOUT.',
        'missing_dependency' => ':module necesita :needs.',
        'missing_dependency_env' => 'Deje fuera los dos (TWS_KIT_WITHOUT=:both) o mantenga lo que falta.',
        'nothing_installed' => 'No se instaló nada. Corrija las variables, borre la carpeta :dir y ejecute el comando de nuevo.',
    ],

    'steps' => [
        'download' => 'Descargando el starter :package (:constraint)',
        'replace' => 'Montando el proyecto con los archivos del starter',
        'modules' => 'Módulos en composer.json: :modules',
        'env' => 'Preparando el .env (post-root-package-install del starter)',
        'install' => 'Instalando las dependencias (composer update)',
        'installer' => 'Claves y base de datos por el instalador del starter (tws:install, sin volver a preguntar)',
    ],

    'failures' => [
        'download' => 'No se pudo descargar el starter :package (vea el mensaje de Composer arriba). No se instaló nada: borre la carpeta :dir y ejecute el comando de nuevo.',
        'unexpected' => 'El paquete descargado no es el starter esperado (:package). No se instaló nada: borre la carpeta :dir y ejecute el comando de nuevo.',
        'replace' => 'No se pudo montar el proyecto en :dir (:reason). Borre la carpeta y ejecute el comando de nuevo.',
        'incomplete' => 'El proyecto en :dir quedó incompleto: el paso ":step" falló (vea la salida arriba).',
        'finish' => 'Para terminar sin empezar de nuevo, después de corregir la causa:',
        'restart' => 'O borre la carpeta :dir y ejecute el comando de nuevo.',
        'leftover' => 'No se pudo borrar :path (¿archivo en uso?). Es del instalador, no del proyecto: bórrela.',
    ],

    'database' => [
        'ready' => 'Base de datos lista: migraciones aplicadas.',
        'unreachable' => 'La base de datos del .env (DB_CONNECTION=:connection, DB_HOST=:host) no respondió: las migraciones quedaron para después.',
        'how' => 'Ajuste las variables DB_* del .env — el docker-compose.yml de desarrollo del kit usa PostgreSQL; para SQLite, DB_CONNECTION=sqlite y borre la línea DB_DATABASE (el archivo database/database.sqlite ya existe) — y ejecute:',
    ],

    'done' => [
        'heading' => 'Proyecto listo en :dir',
        'stack' => 'Interfaz: :stack',
        'modules' => 'Módulos: :modules',
        'next' => 'Próximos pasos:',
    ],
];
