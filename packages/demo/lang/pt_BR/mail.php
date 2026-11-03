<?php

declare(strict_types=1);

// E-mail do formulário de contato da demonstração (pt-BR).

return [

    // Mensagem do formulário de contato da landing → e-mail do time.
    // O assunto continua em contact.mail.subject_line (é a string do módulo
    // de contato); aqui ficam só as partes do CORPO no layout do kit.
    'contact_message' => [
        'preheader' => 'Nova mensagem de :name (:subject).',
        'heading' => 'Nova mensagem do formulário de contato',
        'message_label' => 'Mensagem',
        'reply_hint' => 'Responder este e-mail responde direto para quem escreveu.',
    ],

];
