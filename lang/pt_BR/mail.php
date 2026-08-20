<?php

declare(strict_types=1);

// Strings de e-mails transacionais (pt-BR). Toda string passa por __() — ADR-007.

return [

    // Código de verificação (2FA por e-mail — ADR-006).
    'verification_code' => [
        'subject' => ':platform — Seu código de verificação',
        'intro' => 'Use o código abaixo para confirmar a ação solicitada. Ele é de uso único.',
        'expires' => 'Este código expira em :minutes minutos.',
        'ignore' => 'Se você não solicitou esta ação, ignore este e-mail e considere trocar sua senha.',
    ],

];
