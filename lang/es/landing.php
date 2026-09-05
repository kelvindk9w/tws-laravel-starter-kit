<?php

declare(strict_types=1);

// Strings de la landing oficial (ruta /) — español (ADR-007).
//
// Decisión: el kit promete i18n COMPLETA en los tres idiomas (pt-BR, en, es) y
// tiene un test de paridad de claves. Dejar el inglés aquí como «fallback»
// rompería esa promesa justo en la página que vende el kit — por eso sale
// traducida de verdad, igual que el resto de lang/es.
//
// Los NÚMEROS (clones, tests, horas) no viven aquí: vienen de
// config/landing.php. Las claves `nav.*` y la mitad institucional de `footer.*`
// las usan también la cabecera y el pie del producto.

return [

    'meta' => [
        'title' => 'La base que tu IA no necesita generar',
        'description' => 'Auth, 2FA, API keys, logs enmascarados, subidas, panel y super admin ya listos y probados en un starter kit de Laravel. Clona la base y gasta tus tokens en lo que solo es tuyo.',
    ],

    'a11y' => [
        'skip' => 'Saltar al contenido',
    ],

    'nav' => [
        'features' => 'Recursos',
        'hours' => 'Ahorro',
        'stack' => 'Stack',
        'components' => 'Componentes',
        'login' => 'Entrar',
        'register' => 'Crear cuenta',
    ],

    'hero' => [
        'proof_clones' => 'desarrolladores ya lo clonaron',
        'proof_tests' => 'tests en verde en cada commit',
        'proof_avatar_alt' => 'Foto de perfil de alguien que ya clonó el kit',
        'title_line_1' => 'La base que tu IA',
        'title_line_2' => 'no tiene que generar',
        'subtitle' => 'Auth, 2FA, API keys, logs enmascarados, subidas, panel y admin ya listos y probados. Clónalo y gasta tus tokens en lo que solo es tuyo.',
        'cta_primary' => 'Clonar',
        'cta_demo' => 'Ver la demo',
        'cta_admin_demo' => 'Ver admin demo',
        'note' => 'gratis, MIT',
        'note_secondary' => 'sin tarjeta',
        'screens_heading' => 'Pantallas reales del kit',
        'screens' => [
            'dashboard' => ['label' => 'Panel', 'alt' => 'Panel de usuario del kit: métricas de API keys, gráfico de peticiones y últimas llamadas'],
            'admin' => ['label' => 'Super admin', 'alt' => 'Super admin en Filament: usuarios, peticiones auditadas y envíos de formulario'],
            'ui' => ['label' => 'Componentes', 'alt' => 'Showcase /ui: documentación viva de los componentes del kit'],
            'login' => ['label' => 'Entrar', 'alt' => 'Pantalla de acceso del kit'],
        ],
        'chip_tenancy' => 'Multitenancy',
        'chip_2fa' => '2FA',
        'chip_api_keys' => 'API keys con scopes',
        'chip_lgpd' => 'RGPD/LGPD',
    ],

    'components' => [
        'eyebrow' => 'Componentes',
        'title' => 'No vas a dibujar la tabla otra vez',
        'subtitle' => 'El código que escribes y la pantalla que ve tu cliente son lo mismo. Arrastra la línea y compruébalo.',
        'code_label' => 'Código',
        'screen_label' => 'Pantalla',
        'drag_hint' => 'Arrastra',
        'slider_label' => 'Revelar el código o la pantalla renderizada',
        'screen_alt' => 'La tabla del kit renderizada en el showcase /ui, con badges de estado y acciones por fila',
        'items' => [
            [
                'title' => 'La tabla se vuelve tarjeta',
                'text' => 'Por debajo de sm cada fila cambia de forma y muestra su propia etiqueta. Ninguna columna cortada, ningún scroll lateral escondido.',
            ],
            [
                'title' => 'Una cabecera para todo',
                'text' => 'Landing, showcase, pantallas de acceso y panel comparten un esqueleto. Entrar en la cuenta no puede parecer cambiar de producto.',
            ],
            [
                'title' => 'La identidad en un archivo',
                'text' => 'Colores, tipografía, superficies, radios y motion viven en theme.css. Rebranding es editar un archivo y el .env.',
            ],
        ],
    ],

    'how' => [
        'title' => 'Del clone a la primera pantalla en tres comandos',
        'subtitle' => 'La tarde perdida montando el entorno se acabó en Docker: solo él en tu máquina, nada de PHP, Composer o Node.',
        'steps' => [
            [
                'cursor' => 'clona',
                'title' => 'Clona',
                'text' => 'Un repositorio, una licencia MIT y ninguna dependencia de pago escondida.',
                'command' => 'git clone <repo> mi-proyecto',
            ],
            [
                'cursor' => 'rellena el .env',
                'title' => 'Rellena el .env',
                'text' => 'Nombre, logo, color, idiomas y correos de la plataforma. Nada de texto institucional dentro del código.',
                'command' => 'cp .env.example .env',
            ],
            [
                'cursor' => 'levántalo',
                'title' => 'Levántalo',
                'text' => 'Postgres, Redis, colas, scheduler y buzón de correo suben juntos, con tu usuario.',
                'command' => 'docker compose up -d --build',
            ],
        ],
    ],

    'security' => [
        'title' => 'Seguridad de fábrica',
        'subtitle' => 'La parte para la que nunca hay plazo — y que nadie perdona cuando falta — ya viene implementada, probada y encendida.',
        'items' => [
            ['title' => '2FA por correo', 'text' => 'Código de un solo uso, caducidad corta y bloqueo por intentos — en el acceso y en las acciones sensibles.'],
            ['title' => 'Contraseña de transacción', 'text' => 'Un segundo secreto, con hash aparte del de la contraseña de acceso, para lo que no se puede deshacer.'],
            ['title' => 'API keys con scopes', 'text' => 'Prefijo público, hash en la base, rotación, caducidad por inactividad y denegación por defecto.'],
            ['title' => 'Logs con redaction', 'text' => 'Cada petición auditada de punta a punta, con los campos sensibles enmascarados antes de guardarse.'],
            ['title' => 'Subidas re-codificadas', 'text' => 'Magic bytes, techo de píxeles y re-encode de la imagen: el archivo que entra no es el que se queda.'],
            ['title' => 'Backup cifrado', 'text' => 'Base de datos y archivos hacia R2, con contraseña, validación cruzada y aviso cuando el backup falla.'],
        ],
    ],

    // «Listo para producir»: lo que la base entrega más allá de la seguridad —
    // los dos recursos que vinieron de la landing anterior al retirarse.
    'ready' => [
        'title' => 'Listo para producir',
        'items' => [
            ['title' => 'Mailpit y correos listos', 'text' => 'Servidor de correo de desarrollo ya en el compose, plantilla transaccional única en los tres idiomas, texto plano automático y pantalla de vista previa.'],
            ['title' => 'Hecho de componentes', 'text' => 'Panel, admin y correos montados sobre los mismos componentes reutilizables — documentados y navegables en el showcase /ui.'],
        ],
    ],

    // La cuenta que le importa a quien construye con IA. El NÚMERO viene de
    // config('landing.hours_saved'); cero esconde la línea entera.
    'hours' => [
        'label' => 'horas de trabajo que nadie tiene que generar otra vez',
        'caption' => 'Suma conservadora de lo que ya viene implementado, probado y documentado — auth, 2FA, API keys, multitenancy, panel, admin, subidas, logs, backup y la suite de tests.',
    ],

    'contact' => [
        'anchor_label' => 'Contacto',
    ],

    'footer' => [
        'title' => 'Empieza por tu producto',
        'subtitle' => 'El día 1 de tu proyecto ya trae la seguridad del día 300.',
        'cta' => 'Clonar el repositorio',
        'tech_heading' => 'El stack ya montado',
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

        // Pie institucional del kit (<x-site-footer>) — presente en todas las
        // pantallas, no solo aquí.
        'tagline' => 'Starter kit Laravel para SaaS — base estructural lista para construir.',
        'links_heading' => 'Atajos',
        'showcase' => 'Componentes',
        'demo' => 'Login demo',
        'contact' => 'Contacto',
        'api_status' => 'Estado de la API',
        'rights' => '© :year :company — Todos los derechos reservados',
        'developed_by' => 'Desarrollado por',
    ],

];
