<?php

declare(strict_types=1);

// Correos de autenticación (es): los asuntos, construidos por las clases de
// correo del paquete twstec/kit-auth, y los textos del cuerpo
// (resources/views/mail/messages del paquete). La aplicación gana: la misma
// clave en su lang/ prevalece (ver docs/personalizacao.md).

return [

    'verification_code' => [
        'subject' => ':platform — Tu código de verificación',

        // Código de verificación (2FA por correo).
        'preheader' => 'Tu código expira en :minutes minutos.',
        'heading' => 'Tu código de verificación',
        'intro' => 'Usa el código de abajo para confirmar la acción solicitada. Es de un solo uso.',
        'expires' => 'Este código expira en :minutes minutos.',
        'ignore' => 'Si no solicitaste esta acción, ignora este correo y considera cambiar tu contraseña.',
    ],

    'login_code' => [
        'subject' => ':platform — Tu código de acceso',

        // Código del segundo factor del INICIO DE SESIÓN (mismo correo de código, otra finalidad).
        'preheader' => 'Tu código de acceso expira en :minutes minutos.',
        'heading' => 'Tu código de acceso',
        'intro' => 'La contraseña de tu cuenta se escribió correctamente y falta un paso para completar el inicio de sesión. Si fuiste tú, usa el código de abajo. Es de un solo uso.',
        'expires' => 'Este código expira en :minutes minutos.',
        'ignore' => '¿No fuiste tú? No compartas este código con nadie y cambia tu contraseña ahora: quien intentó entrar conoce tu contraseña actual.',
    ],

    'email_verification' => [
        'subject' => ':platform — Confirma tu correo',

        // Recuperación de contraseña (bug de QA #9 — antes llegaba en inglés).
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

];
