<?php

declare(strict_types=1);

// Asuntos de los correos de autenticación (es), construidos por las clases de
// correo del paquete twstec/kit-auth. El cuerpo de cada correo y sus textos
// son del front. La aplicación gana: la misma clave en su lang/ prevalece.

return [

    'verification_code' => [
        'subject' => ':platform — Tu código de verificación',
    ],
    'login_code' => [
        'subject' => ':platform — Tu código de acceso',
    ],
    'email_verification' => [
        'subject' => ':platform — Confirma tu correo',
    ],
    'password_reset' => [
        'subject' => ':platform — Restablecer contraseña',
    ],

];
