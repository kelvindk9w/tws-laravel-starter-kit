<?php

declare(strict_types=1);

// Strings de e-mails transacionais (pt-BR). Toda string passa por __() — ADR-007.

return [

    // Aviso prévio de expiração de chave de API por inatividade (ADR-006).
    'api_key_inactivity' => [
        'subject' => ':platform — Sua chave de API será desativada por inatividade',
        'intro' => 'A chave de API ":name" (:code, :key) está sem uso e será desativada automaticamente por inatividade.',
        'expires' => 'A desativação acontece em :days dias.',
        'action' => 'Para mantê-la ativa, basta fazer uma requisição autenticada com ela. Se não precisar mais dela, recomendamos revogá-la no painel.',
        'ignore' => 'Se você não reconhece esta chave, revogue-a imediatamente e troque suas credenciais.',
    ],

    // Código de verificação (2FA por e-mail — ADR-006).
    'verification_code' => [
        'subject' => ':platform — Seu código de verificação',
        'intro' => 'Use o código abaixo para confirmar a ação solicitada. Ele é de uso único.',
        'expires' => 'Este código expira em :minutes minutos.',
        'ignore' => 'Se você não solicitou esta ação, ignore este e-mail e considere trocar sua senha.',
    ],

];
