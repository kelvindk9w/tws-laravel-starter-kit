<?php

declare(strict_types=1);

// Subjects of the authentication e-mails (en), built by the twstec/kit-auth
// mail classes. Each e-mail body and its strings belong to the front. The
// application wins: the same key in its lang/ takes precedence.

return [

    'verification_code' => [
        'subject' => ':platform — Your verification code',
    ],
    'login_code' => [
        'subject' => ':platform — Your sign-in code',
    ],
    'email_verification' => [
        'subject' => ':platform — Confirm your email',
    ],
    'password_reset' => [
        'subject' => ':platform — Password reset',
    ],

];
