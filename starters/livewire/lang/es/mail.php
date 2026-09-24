<?php

declare(strict_types=1);

// Cadenas de correos transaccionales (es). Toda cadena pasa por __().
// El cuerpo de los correos vive en resources/views/mail/messages/**, sobre el
// layout único <x-email::layouts.kit>. Ver docs/emails.md. El pie común a
// todos (mail.footer.*) viene del paquete twstec/kit-foundation.

return [

    // Aviso previo de expiración de clave de API por inactividad.
    'api_key_inactivity' => [
        'subject' => ':platform — Tu clave de API se desactivará por inactividad',
        'preheader' => 'Una clave sin uso se desactivará en :days días.',
        'heading' => 'Una de tus claves de API está por desactivarse',
        'intro' => 'La clave de abajo no se está usando y se desactivará automáticamente por inactividad.',
        'name_label' => 'Nombre de la clave',
        'code_label' => 'Código público',
        'key_label' => 'Clave pública',
        'expires' => 'La desactivación ocurre en :days días.',
        'action' => 'Para mantenerla activa, basta con hacer una solicitud autenticada con ella. Si ya no la necesitas, te recomendamos revocarla en el panel.',
        'cta' => 'Abrir mis claves',
        'ignore' => 'Si no reconoces esta clave, revócala de inmediato y cambia tus credenciales.',
    ],

    // Código de verificación (2FA por correo).
    'verification_code' => [
        'subject' => ':platform — Tu código de verificación',
        'preheader' => 'Tu código expira en :minutes minutos.',
        'heading' => 'Tu código de verificación',
        'intro' => 'Usa el código de abajo para confirmar la acción solicitada. Es de un solo uso.',
        'expires' => 'Este código expira en :minutes minutos.',
        'ignore' => 'Si no solicitaste esta acción, ignora este correo y considera cambiar tu contraseña.',
    ],

    // Código del segundo factor del INICIO DE SESIÓN (mismo correo de código, otra finalidad).
    'login_code' => [
        'subject' => ':platform — Tu código de acceso',
        'preheader' => 'Tu código de acceso expira en :minutes minutos.',
        'heading' => 'Tu código de acceso',
        'intro' => 'La contraseña de tu cuenta se escribió correctamente y falta un paso para completar el inicio de sesión. Si fuiste tú, usa el código de abajo. Es de un solo uso.',
        'expires' => 'Este código expira en :minutes minutos.',
        'ignore' => '¿No fuiste tú? No compartas este código con nadie y cambia tu contraseña ahora: quien intentó entrar conoce tu contraseña actual.',
    ],

    // Recuperación de contraseña (bug de QA #9 — antes llegaba en inglés).
    'email_verification' => [
        'subject' => ':platform — Confirma tu correo',
        'preheader' => 'Falta un paso: confirma tu correo para habilitar tu cuenta.',
        'heading' => 'Confirma tu correo',
        'intro' => 'Tu cuenta fue creada. Para habilitar el acceso al panel, confirma que esta dirección es tuya.',
        'action' => 'Confirmar correo',
        'expires' => 'Este enlace expira en :minutes minutos. Después, solicita uno nuevo en la pantalla de aviso.',
        'fallback' => 'Si el botón no funciona, copia y pega esta dirección en tu navegador:',
        'ignore' => 'Si no creaste esta cuenta, ignora este correo: sin la confirmación, no se habilita.',
    ],

    'password_reset' => [
        'subject' => ':platform — Restablecer contraseña',
        'preheader' => 'Enlace de restablecimiento válido por :minutes minutos.',
        'heading' => 'Restablecer tu contraseña',
        'intro' => 'Recibes este correo porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.',
        'action' => 'Restablecer contraseña',
        'expires' => 'Este enlace expira en :minutes minutos.',
        'fallback' => 'Si el botón no funciona, copia y pega esta dirección en tu navegador:',
        'ignore' => 'Si no solicitaste el restablecimiento, no necesitas hacer nada.',
    ],

    // Mensaje del formulario de contacto de la landing → correo del equipo.
    // El asunto sigue en contact.mail.subject_line; aquí solo el CUERPO.
    'contact_message' => [
        'preheader' => 'Nuevo mensaje de :name (:subject).',
        'heading' => 'Nuevo mensaje del formulario de contacto',
        'message_label' => 'Mensaje',
        'reply_hint' => 'Responder este correo contesta directamente a quien escribió.',
    ],

    // Pantalla de vista previa de correos (/mail-preview) — solo en desarrollo.
    'preview' => [
        'title' => 'Vista previa de los correos',
        'subtitle' => 'Todos los correos transaccionales del kit con datos de ejemplo, en los tres idiomas y en los dos temas. Herramienta de desarrollo: en producción esta ruta responde 404.',
        'list_heading' => 'Correos',
        'language' => 'Idioma',
        'scheme' => 'Tema',
        'subject' => 'Asunto',
        'plain_text' => 'Versión en texto plano',
        'open_html' => 'Abrir el HTML',
        'open_text' => 'Ver el texto plano',
        'mailpit_hint' => 'Para comprobar cómo llega el correo de verdad (cabeceras, multipart, adjuntos), dispara el flujo y abre Mailpit en http://localhost:18025.',
        'emails' => [
            'email-verification' => 'Confirmación de correo',
            'verification-code' => 'Código de verificación',
            'login-code' => 'Código de acceso (inicio de sesión)',
            'password-reset' => 'Restablecer contraseña',
            'api-key-inactivity' => 'Clave de API inactiva',
            'contact-message' => 'Formulario de contacto',
        ],
        'locales' => ['pt_BR' => 'Português', 'en' => 'English', 'es' => 'Español'],
        'schemes' => ['light' => 'Claro', 'dark' => 'Oscuro'],
    ],

];
