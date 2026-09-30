<?php

declare(strict_types=1);

// Textos del comando único (composer create-project twstec/kit) — es.

return [
    'intro' => 'TWS Laravel Starter Kit — creando el proyecto en :dir',
    'always' => 'Siempre incluidos: Base (twstec/kit-foundation — seguridad, auditoría, idioma, correo) y Autenticación (twstec/kit-auth — inicio de sesión, registro, verificación de correo, segundo factor).',
    'aborted' => 'No se instaló nada. Borre la carpeta :dir y ejecute el comando de nuevo cuando quiera.',
    'confirm' => '¿Crear el proyecto así?',

    'name' => [
        'label' => 'Nombre del proyecto',
        'hint' => 'Letras minúsculas, números y guion. Se vuelve la dirección http://<nombre>.localhost, el nombre de los contenedores y el de la base de datos.',
    ],

    'slot' => [
        'label' => 'Número del proyecto (los puertos)',
        'hint' => 'Todos los puertos terminan en él: sitio 808N, correos 802N, Vite 803N, base de datos 804N. De 0 a 9; después, 10 a 99 (la centena siguiente: 818N…).',
        'intro' => 'Cada proyecto usa un número, y sus puertos terminan en ese número (el 2 es el sitio en el 8082, los correos en el 8022…).',
        'used' => ':slot ya lo usa :owners',
        'next_hundred' => 'De 0 a 9 no hay número con los cuatro puertos libres: la sugerencia es :slot, en la centena siguiente (mismo patrón, 818N, 812N…).',
        'invalid' => 'Escriba un número de 0 a 99.',
    ],

    'dev' => [
        'docker_unavailable' => 'Docker no respondió desde aquí: los nombres de los otros proyectos no se pueden comprobar, y los puertos solo por lo que se puede abrir en esta máquina.',
        'other_program' => 'otro programa',
    ],

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
        'summary' => "Proyecto: :name — :site (correos: :mail)\nInterfaz: :stack\nMódulos opcionales: :modules",
        'from_environment' => 'Elección (sin preguntas): proyecto :name, número :slot; :stack; módulos opcionales: :modules.',
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
        'in_variable' => 'En :variable:',
        'name_format' => 'el nombre ":name" no sirve: use letras minúsculas, números y guion, empezando por letra (sugerencia: :suggestion).',
        'name_length' => 'el nombre ":name" debe tener de 2 a 40 caracteres (sugerencia: :suggestion).',
        'name_taken' => 'ya existe un proyecto Docker llamado :name en esta máquina (contenedores o volúmenes, aunque esté detenido). Use otro nombre — sugerencia: :suggestion.',
        'slot_invalid' => 'número de proyecto inválido: :slot. Use de 0 a 99 (:variable).',
        'slot_busy' => 'el número :slot está en uso — :occupants.',
        'slot_suggestion' => 'El primer número con los cuatro puertos libres es :suggestion.',
        'no_free_slot' => 'Ningún número de 0 a 99 tiene los cuatro puertos libres. Detenga proyectos que no esté usando (docker compose stop, en la carpeta de cada uno) y ejecute de nuevo.',
        'expose_invalid' => 'Valor inválido en TWS_KIT_EXPOSE_DB: :value. Use 1 (publicar la base de datos) o 0.',
    ],

    'steps' => [
        'download' => 'Descargando el starter :package (:constraint)',
        'replace' => 'Montando el proyecto con los archivos del starter',
        'modules' => 'Módulos en composer.json: :modules',
        'env' => 'Preparando el .env (post-root-package-install del starter)',
        'install' => 'Instalando las dependencias (composer update)',
        'installer' => 'Claves, Docker de desarrollo y base de datos por el instalador del starter (tws:install, sin volver a preguntar)',
    ],

    'failures' => [
        'download' => 'No se pudo descargar el starter :package (vea el mensaje de Composer arriba). No se instaló nada: borre la carpeta :dir y ejecute el comando de nuevo.',
        'unexpected' => 'El paquete descargado no es el starter esperado (:package). No se instaló nada: borre la carpeta :dir y ejecute el comando de nuevo.',
        'replace' => 'No se pudo montar el proyecto en :dir (:reason). Borre la carpeta y ejecute el comando de nuevo.',
        'incomplete' => 'El proyecto en :dir quedó incompleto: el paso ":step" falló (vea la salida arriba).',
        'finish' => 'Para terminar sin empezar de nuevo, después de corregir la causa:',
        'finish_after_extensions' => 'Con las extensiones instaladas, para terminar sin empezar de nuevo:',
        'restart' => 'O borre la carpeta :dir y ejecute el comando de nuevo.',
        'leftover' => 'No se pudo borrar :path (¿archivo en uso?). Es del instalador, no del proyecto: bórrela.',
    ],

    // Extensiones de PHP que faltan (el `composer update` las rechazó).
    'extensions' => [
        'missing' => 'Faltan extensiones de PHP en esta máquina: :extensions. Hay dos salidas:',
        'install' => '1) Instalar las extensiones en el PHP de esta máquina (compruebe con `php -m`):',
        'windows' => '   • Windows: en el php.ini (`php --ini` muestra dónde está), quite el ";" del inicio de las líneas :lines (el PHP de windows.php.net ya trae los archivos).',
        'linux' => '   • Ubuntu/Debian: sudo apt install :packages',
        'mac' => '   • macOS (Homebrew): el PHP de `brew install php` ya trae esas extensiones; compruebe qué PHP usa la terminal (`which php`).',
        'docker' => '2) O usar el camino solo con Docker, que ya trae todo: borre esta carpeta, descargue twstec-kit (Code → Download ZIP) y, dentro de su carpeta, ejecute `docker compose run --rm instalar`.',
        'windows_horizon' => 'Windows: Horizon (el panel de las colas) necesita las extensiones pcntl y posix, que el PHP de Windows no tiene — se ignoraron en la instalación, y solo Horizon queda fuera. El resto funciona normalmente; para procesar la cola, use `php artisan queue:work`. En los próximos comandos de Composer en este proyecto, defina antes `$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"`. El camino solo con Docker (docker compose run --rm instalar) ejecuta todo, Horizon incluido.',
    ],

    'database' => [
        'ready' => 'Base de datos lista: migraciones aplicadas.',
        'unreachable' => 'La base de datos del .env (DB_CONNECTION=:connection, DB_HOST=:host) no respondió: las migraciones quedaron para después.',
        'how' => 'Ajuste las variables DB_* del .env — el docker-compose.yml de desarrollo del kit usa PostgreSQL; para SQLite, DB_CONNECTION=sqlite y borre la línea DB_DATABASE (el archivo database/database.sqlite ya existe) — y ejecute:',
        'docker' => 'La base de datos del proyecto corre en Docker: el primer `docker compose up -d` crea la base y ejecuta las migraciones solo.',
    ],

    'done' => [
        'heading' => 'Proyecto listo en :dir',
        'stack' => 'Interfaz: :stack',
        'modules' => 'Módulos: :modules',
        'next' => 'Próximos pasos:',
        'docker' => 'Docker de desarrollo: proyecto :name, número :slot (los puertos terminan en :slot; todo en el .env).',
        'site' => 'Sitio: :url',
        'mail' => 'Correos enviados (Mailpit): :url',
        'database_exposed' => 'Base de datos: publicada en el puerto :port de esta máquina (127.0.0.1), con la contraseña del .env.',
        'database_internal' => 'Base de datos y Redis: solo dentro de Docker (para publicar la base en el puerto :port, COMPOSE_PROFILES=db-port en el .env).',
        'open' => 'Después, abra :url en el navegador (la primera subida tarda unos minutos: construye las imágenes).',
    ],

    // Dentro del contenedor del instalador (docker compose run --rm instalar):
    // la carpeta es la del ZIP, y el camino es el de Docker.
    'docker' => [
        'intro' => 'TWS Laravel Starter Kit — creando el proyecto en esta carpeta',
        'aborted' => 'No se instaló nada. Ejecute `docker compose run --rm instalar` de nuevo cuando quiera.',
        'errors' => [
            'nothing_installed' => 'No se instaló nada. Corrija las variables y ejecute `docker compose run --rm instalar` de nuevo.',
        ],
        'failures' => [
            'download' => 'No fue posible descargar el starter :package (vea el mensaje de Composer arriba). No se instaló nada: revise la conexión y ejecute `docker compose run --rm instalar` de nuevo.',
            'unexpected' => 'El paquete descargado no es el starter esperado (:package). No se instaló nada: ejecute `docker compose run --rm instalar` de nuevo.',
            'replace' => 'No fue posible montar el proyecto en esta carpeta (:reason). Descargue el ZIP de twstec-kit de nuevo, en una carpeta nueva, y ejecute el instalador.',
            'incomplete' => 'El proyecto en esta carpeta quedó incompleto: el paso ":step" falló (vea la salida arriba).',
            'restart' => 'Descargue el ZIP de twstec-kit de nuevo, en una carpeta nueva, y ejecute `docker compose run --rm instalar` (borre esta carpeta).',
        ],
        'done' => [
            'heading' => 'Proyecto listo en esta carpeta',
        ],
    ],
];
