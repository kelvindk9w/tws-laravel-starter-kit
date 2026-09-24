<?php

declare(strict_types=1);

// Assuntos dos e-mails de autenticação (pt-BR), montados pelas classes de
// e-mail do pacote twstec/kit-auth. O corpo de cada e-mail e as strings dele
// são do front. O aplicativo vence: a mesma chave no lang/ dele prevalece.

return [

    'verification_code' => [
        'subject' => ':platform — Seu código de verificação',
    ],
    'login_code' => [
        'subject' => ':platform — Seu código de acesso',
    ],
    'email_verification' => [
        'subject' => ':platform — Confirme seu e-mail',
    ],
    'password_reset' => [
        'subject' => ':platform — Redefinição de senha',
    ],

];
