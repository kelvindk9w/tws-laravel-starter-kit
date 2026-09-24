<?php

declare(strict_types=1);

// Assunto do aviso de inatividade de chave de API (pt-BR), montado pela classe
// de e-mail do pacote twstec/kit-accounts. O corpo do e-mail e as strings dele
// são do front. O aplicativo vence: a mesma chave no lang/ dele prevalece.

return [

    'api_key_inactivity' => [
        'subject' => ':platform — Sua chave de API será desativada por inatividade',
    ],

];
