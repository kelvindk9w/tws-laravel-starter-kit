<?php

declare(strict_types=1);

// Cadenas de correos transaccionales (es). Toda cadena pasa por __() — ADR-007.

return [

    // Aviso previo de expiración de clave de API por inactividad (ADR-006).
    'api_key_inactivity' => [
        'subject' => ':platform — Tu clave de API será desactivada por inactividad',
        'intro' => 'La clave de API ":name" (:code, :key) está sin uso y será desactivada automáticamente por inactividad.',
        'expires' => 'La desactivación ocurre en :days días.',
        'action' => 'Para mantenerla activa, basta hacer una petición autenticada con ella. Si ya no la necesitas, te recomendamos revocarla en el panel.',
        'ignore' => 'Si no reconoces esta clave, revócala de inmediato y cambia tus credenciales.',
    ],

    // Código de verificación (2FA por correo — ADR-006).
    'verification_code' => [
        'subject' => ':platform — Tu código de verificación',
        'intro' => 'Usa el código a continuación para confirmar la acción solicitada. Es de uso único.',
        'expires' => 'Este código expira en :minutes minutos.',
        'ignore' => 'Si no solicitaste esta acción, ignora este correo y considera cambiar tu contraseña.',
    ],

];
