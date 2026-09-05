<?php

declare(strict_types=1);

// Cadenas de la landing pública (/) — es (ADR-007). NUNCA texto fijo en views.

return [

    'nav' => [
        'features' => 'Recursos',
        'hours' => 'Ahorro',
        'stack' => 'Stack',
        'components' => 'Componentes',
        'login' => 'Entrar',
        'demo' => 'Probar demo',
        'register' => 'Crear cuenta',
        'dashboard' => 'Ir al panel',
        'menu' => 'Menú',
        'open_menu' => 'Abrir menú de navegación',
    ],

    'hero' => [
        'title' => 'Tu SaaS Laravel en producción en días, no meses',
        'subtitle' => 'Autenticación con 2FA, claves de API con rotación, multitenancy, panel Livewire, admin Filament, uploads seguros, backup y tests — todo listo y auditado. Tú construyes solo lo que es de tu producto.',
        'cta_components' => 'Explorar componentes',
        'cta_demo' => 'Probar demo',
        'cta_admin_demo' => 'Ver admin demo',
        'cta_register' => 'Crear cuenta',
        'screenshot_alt' => 'Captura de pantalla real del panel del kit',
        'mockup_title' => 'Panel',
        'mockup_row_1' => 'Claves de API activas',
        'mockup_row_2' => 'Proyectos',
        'mockup_row_3' => 'Peticiones auditadas',
    ],

    'stack' => [
        'heading' => 'Stack actual, probado en producción',
        'items' => ['Laravel 13', 'PHP 8.4', 'PostgreSQL 18', 'Redis 8', 'Livewire 4', 'Filament 5', 'Tailwind 4', 'Horizon', 'Pest', 'Docker'],
        'dev_note' => 'Entorno dev completo en compose: Mailpit (bandeja de correos), Horizon (colas) y scheduler — sin instalar nada más que Docker.',
    ],

    'hours' => [
        'heading' => 'Horas que no vas a tener que gastar',
        'subtitle' => 'Estimación conservadora de lo que ya viene implementado, probado y documentado.',
        'items' => [
            ['task' => 'Autenticación completa con 2FA por correo y bloqueo por intentos', 'hours' => 40],
            ['task' => 'Contraseña de transacción + confirmación de acciones sensibles', 'hours' => 24],
            ['task' => 'Claves de API con scopes, rotación y expiración por inactividad', 'hours' => 40],
            ['task' => 'Multitenancy por proyectos con aislamiento de datos', 'hours' => 24],
            ['task' => 'Panel de usuario (Livewire) + super admin (Filament)', 'hours' => 56],
            ['task' => 'Uploads seguros con re-codificación de imagen y URLs firmadas', 'hours' => 24],
            ['task' => 'Request logging, auditoría y redacción de datos (LGPD)', 'hours' => 16],
            ['task' => 'Backup cifrado a R2 con validación cruzada', 'hours' => 16],
            ['task' => 'Colas con Horizon, CSP y rate limiting', 'hours' => 16],
            ['task' => 'Suite de tests Pest + E2E Playwright', 'hours' => 24],
        ],
        'total_label' => 'Total ahorrado',
        'total_value' => ':hours horas',
        'total_unit' => 'horas de trabajo ya hechas',
        'total_caption' => 'Estimación conservadora de lo que ya viene implementado, probado y documentado — detallado abajo.',
    ],

    'features' => [
        'heading' => 'Todo lo que un SaaS serio necesita',
        'subtitle' => 'No es un boilerplate de juguete: cada recurso sigue un checklist de seguridad y tiene tests.',
        'items' => [
            ['icon' => 'shield-check', 'title' => 'Autenticación + 2FA', 'description' => 'Registro, inicio de sesión, restablecimiento de contraseña y verificación por código de correo, con bloqueo por intentos y sesión regenerada.'],
            ['icon' => 'lock-closed', 'title' => 'Contraseña de transacción', 'description' => 'Segundo secreto (hash separado) para confirmar acciones sensibles, con token de uso único y corta duración.'],
            ['icon' => 'key', 'title' => 'Claves de API con rotación', 'description' => 'Claves pk_/sk_ con scopes granulares, período de gracia en la rotación y desactivación por inactividad.'],
            ['icon' => 'building-office', 'title' => 'Multitenancy por proyectos', 'description' => 'Cada usuario organiza recursos en proyectos con aislamiento garantizado por global scopes y tests.'],
            ['icon' => 'squares-2x2', 'title' => 'Panel Livewire', 'description' => 'Dashboard, claves de API, proyectos, notificaciones y perfil en Livewire 4 — UI directa, modales en lugar de navegación.'],
            ['icon' => 'cog-6-tooth', 'title' => 'Super admin Filament', 'description' => 'Panel /admin en Filament 5 restringido a administradores, con allowlist de IP para producción.'],
            ['icon' => 'arrow-up-tray', 'title' => 'Uploads seguros', 'description' => 'Validación por firma real del archivo, re-codificación de imágenes en GD y URLs firmadas de corta duración.'],
            ['icon' => 'clipboard-document-list', 'title' => 'Auditoría y logs', 'description' => 'Request logging en base de datos y archivo con redacción de datos sensibles (LGPD) y retención configurable.'],
            ['icon' => 'archive-box', 'title' => 'Backup y colas', 'description' => 'Dump PostgreSQL cifrado a R2 con webhook de validación cruzada, y Horizon para las colas.'],
            ['icon' => 'beaker', 'title' => 'Tests de verdad', 'description' => 'Cobertura Pest de feature en todos los módulos + E2E Playwright — validación de contenido, no solo de estado.'],
            ['icon' => 'language', 'title' => 'i18n nativo', 'description' => 'Toda cadena pasa por archivos de idioma (lang/), nunca texto fijo en views — multi-idioma listo desde el inicio.'],
            ['icon' => 'server-stack', 'title' => 'Docker autocontenido', 'description' => 'Solo Docker en tu máquina: compose levanta app, base de datos, Redis, colas y scheduler — incluso el stack de producción.'],
        ],
    ],

    'cta' => [
        'heading' => '¿Listo para construir?',
        'subtitle' => 'Crea tu cuenta y explora el panel, o sumérgete en el código: cada decisión está documentada en ADRs.',
        'repo' => 'Ver el código en el repositorio',
        'register' => 'Crear cuenta',
        'demo' => 'Probar demo',
        'login' => 'Entrar',
    ],

    'footer' => [
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
