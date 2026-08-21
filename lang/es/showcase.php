<?php

declare(strict_types=1);

// Cadenas del showcase de componentes (/ui) — es (ADR-007). NUNCA texto fijo en views.

return [

    'title' => 'Componentes UI',
    'subtitle' => 'Documentación viva de los componentes Blade del kit. Copia y usa: <x-button>, <x-alert> y compañía.',

    'snippets' => [
        'copy' => 'Copiar',
        'copied' => '¡Copiado!',
        'copied_toast' => 'Snippet copiado al portapapeles.',
    ],

    'categories' => [
        'theme' => 'Tema',
        'buttons' => 'Botones',
        'alerts' => 'Alertas',
        'badges' => 'Badges',
        'forms' => 'Formularios',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Estado vacío',
        'loading' => 'Carga',
        'form_example' => 'Formulario completo',
    ],

    'theme_tokens' => [
        'guide' => 'La identidad visual vive en UN archivo: resources/css/theme.css (bloque @theme de Tailwind 4: colores, fuentes, radios, motion) + config/platform.php alimentado por el .env (nombre, logo, color primario). Para rebrandear: edita ambos y todo el kit — landing, panel, admin y correos — lo refleja.',
        'brand' => 'Color de marca',
        'brand_hint' => 'PLATFORM_PRIMARY_COLOR en el .env se vuelve --brand en el <head> (sin rebuild) y --color-brand en las utilidades (bg-brand, text-brand).',
        'fonts' => 'Tipografía',
        'font_display_sample' => 'Display (Space Grotesk) — títulos',
        'font_body_sample' => 'Cuerpo (Instrument Sans) — textos y UI',
        'fonts_hint' => '--font-display y --font-sans en theme.css; clases font-display / font-sans.',
        'radii' => 'Radios de borde',
        'radii_hint' => '--radius-lg / --radius-xl en theme.css — el lenguaje usa rounded-lg y rounded-xl.',
        'motion' => 'Motion',
        'motion_hint' => '--ease-out / --ease-in-out fuertes; UI por debajo de 300ms; todo respeta prefers-reduced-motion.',
        'modes' => 'Claro, oscuro o sistema',
        'modes_hint' => 'Toggle de 3 estados arriba de esta página. Por defecto = preferencia del SO, sin flash al cargar; elección persistida en el dispositivo y en la cuenta.',
    ],

    'buttons' => [
        'guide' => 'Cuándo usar: acción principal del bloque = primario (máximo uno por bloque); apoyo = outline o secondary; navegación discreta = ghost; destructiva = danger. A11y: foco visible y feedback de presión en todos; al enviar, deshabilita y muestra el spinner dentro del botón.',
        'variants' => 'Variantes',
        'sizes' => 'Tamaños',
        'states' => 'Estados',
        'primary' => 'Primario',
        'secondary' => 'Secundario',
        'outline' => 'Outline',
        'ghost' => 'Ghost',
        'danger' => 'Peligro',
        'small' => 'Pequeño',
        'medium' => 'Mediano',
        'large' => 'Grande',
        'disabled' => 'Deshabilitado',
        'loading' => 'Cargando',
        'as_link' => 'Como enlace',
    ],

    'alerts' => [
        'guide' => 'Cuándo usar: feedback persistente en el contexto del contenido (no se oculta solo — para eso usa toast). A11y: role="alert" hace que los lectores de pantalla lo anuncien al instante.',
        'success_title' => 'Todo listo',
        'success' => 'Tu cambio se guardó con éxito.',
        'warning_title' => 'Atención',
        'warning' => 'Tu clave de API expira en 7 días por inactividad.',
        'error_title' => 'Error en la operación',
        'error' => 'No fue posible procesar la solicitud. Intenta de nuevo.',
        'info_title' => 'Información',
        'info' => 'Una nueva versión de la plataforma estará disponible pronto.',
    ],

    'badges' => [
        'guide' => 'Estados cortos y escaneables. No uses como botón ni para texto largo; brand para destaque de marca, neutral por defecto.',
        'active' => 'Activo',
        'pending' => 'Pendiente',
        'blocked' => 'Bloqueado',
        'beta' => 'Beta',
        'brand' => 'De la marca',
        'neutral' => 'Neutro',
    ],

    'forms' => [
        'guide' => 'Label siempre visible (nunca placeholder como label), hint para el formato esperado y error junto al campo. type="password" ya incluye el botón ojo (revelar/ocultar).',
        'password_label' => 'Contraseña',
        'password_hint' => 'Haz clic en el ojo para revelar.',
        'message_label' => 'Mensaje',
        'message_placeholder' => 'Cuenta el contexto en pocas líneas…',
        'text_label' => 'Nombre del proyecto',
        'text_placeholder' => 'Mi tienda',
        'text_hint' => 'Puede cambiarse después.',
        'with_error_label' => 'Correo electrónico',
        'with_error_message' => 'Ingresa un correo válido.',
        'disabled_label' => 'Campo deshabilitado',
        'select_label' => 'Plan',
        'select_option_1' => 'Gratuito',
        'select_option_2' => 'Pro',
        'select_option_3' => 'Empresarial',
        'checkbox' => 'Acepto los términos de uso',
        'checkbox_checked' => 'Recibir novedades por correo',
        'toggle' => 'Notificaciones por correo',
        'toggle_on' => '2FA obligatorio',
        'usage' => 'Uso: <x-input>, <x-select>, <x-checkbox>, <x-toggle> — label, hint y estado de error incluidos.',
    ],

    'cards' => [
        'guide' => 'Agrupa contenido relacionado; el pie es un slot opcional para acciones. Evita anidar cards.',
        'simple_title' => 'Card simple',
        'simple_body' => 'Cuerpo del card con texto de apoyo. Úsalo para agrupar información relacionada.',
        'footer_title' => 'Card con pie',
        'footer_body' => 'El pie es un slot opcional, ideal para acciones.',
        'footer_action' => 'Guardar',
    ],

    'modal' => [
        'guide' => 'Confirmaciones y flujos cortos sin salir de la pantalla. Cierra por Esc, backdrop o botón; la entrada es una transition interrumpible (scale 0.95 + fade).',
        'open' => 'Abrir modal',
        'title' => 'Confirmar acción',
        'body' => 'Este modal es un componente Blade real (<x-modal>): abre por data-modal-open y cierra por backdrop, botón o Esc. La animación es una CSS transition (interrumpible) y el JS vive en resources/js/ui.js, servido por Vite.',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

    'toast' => [
        'guide' => 'Feedback efímero de acción completada — se oculta solo. No uses para errores que exigen una decisión del usuario (usa <x-alert>).',
        'demo_button' => 'Disparar toast',
        'demo_message' => 'Preferencias guardadas con éxito.',
        'flash_note' => 'Para flash de sesión, renderiza <x-toast> con session(\'status\') en tu layout. El comportamiento (abrir, auto-ocultar) vive en resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copiado al portapapeles.',

    'empty_state' => [
        'guide' => 'Primera experiencia de un área vacía: di qué es, por qué importa y cuál es la siguiente acción.',
        'title' => 'Ningún proyecto aún',
        'description' => 'Los proyectos agrupan tus claves de API y uploads. Crea el primero para empezar.',
        'action' => 'Crear proyecto',
    ],

    'loading' => [
        'guide' => 'Jerarquía de espera: spinner dentro del botón para envíos; skeleton para contenido que está llegando (listas, cards); overlay de pantalla completa es el ÚLTIMO recurso.',
        'sizes' => 'Tamaños',
        'in_button' => 'En botones',
        'saving' => 'Guardando…',
        'skeleton_heading' => 'Skeleton (contenido llegando)',
        'skeleton_hint' => 'Muestra la ESTRUCTURA que viene — percepción de rapidez mayor que spinner. Shimmer sutil, desactivado con prefers-reduced-motion. En el panel combina con wire:loading (ver Proyectos).',
        'overlay_heading' => 'Overlay de pantalla completa (uso restringido)',
        'overlay_restriction' => 'SOLO para carga inicial de un área entera o acciones largas y raras (ej.: generar un reporte pesado). Bloquea toda la pantalla — para todo lo demás usa skeleton o el spinner del botón.',
        'overlay_demo' => 'Ver por 1,5 s',
    ],

    'form_example' => [
        'guide' => 'El formulario de contacto de la landing montado con los componentes del kit: input, input password, select, textarea, checkbox y botón con estado de carga.',
        'subject' => 'Asunto',
        'subject_options' => ['Sugerencia', 'Reclamo', 'Otro'],
        'submit' => 'Enviar mensaje',
    ],

    'components' => [
        'spinner_label' => 'Cargando',
        'loading_label' => 'Cargando contenido',
    ],

];
