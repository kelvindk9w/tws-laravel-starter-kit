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
        'buttons' => 'Botones',
        'alerts' => 'Alertas',
        'badges' => 'Badges',
        'forms' => 'Formularios',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Estado vacío',
        'loading' => 'Carga',
    ],

    'buttons' => [
        'variants' => 'Variantes',
        'sizes' => 'Tamaños',
        'states' => 'Estados',
        'primary' => 'Primario',
        'secondary' => 'Secundario',
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
        'active' => 'Activo',
        'pending' => 'Pendiente',
        'blocked' => 'Bloqueado',
        'beta' => 'Beta',
        'brand' => 'De la marca',
        'neutral' => 'Neutro',
    ],

    'forms' => [
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
        'simple_title' => 'Card simple',
        'simple_body' => 'Cuerpo del card con texto de apoyo. Úsalo para agrupar información relacionada.',
        'footer_title' => 'Card con pie',
        'footer_body' => 'El pie es un slot opcional, ideal para acciones.',
        'footer_action' => 'Guardar',
    ],

    'modal' => [
        'open' => 'Abrir modal',
        'title' => 'Confirmar acción',
        'body' => 'Este modal es un componente Blade real (<x-modal>): abre por data-modal-open y cierra por backdrop, botón o Esc. La animación es una CSS transition (interrumpible) y el JS vive en resources/js/ui.js, servido por Vite.',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
    ],

    'toast' => [
        'demo_button' => 'Disparar toast',
        'demo_message' => 'Preferencias guardadas con éxito.',
        'flash_note' => 'Para flash de sesión, renderiza <x-toast> con session(\'status\') en tu layout. El comportamiento (abrir, auto-ocultar) vive en resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copiado al portapapeles.',

    'empty_state' => [
        'title' => 'Ningún proyecto aún',
        'description' => 'Los proyectos agrupan tus claves de API y uploads. Crea el primero para empezar.',
        'action' => 'Crear proyecto',
    ],

    'loading' => [
        'sizes' => 'Tamaños',
        'in_button' => 'En botones',
        'saving' => 'Guardando…',
    ],

    'components' => [
        'spinner_label' => 'Cargando',
    ],

];
