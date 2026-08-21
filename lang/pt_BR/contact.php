<?php

declare(strict_types=1);

// Formulário de contato da landing (/) — pt-BR (ADR-007).

return [

    'heading' => 'Fale com a gente',
    'subtitle' => 'Sugestão, reclamação ou outro assunto — sua mensagem chega por e-mail ao time e respondemos no endereço informado.',

    'form' => [
        'name' => 'Nome',
        'name_placeholder' => 'Seu nome',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'subject' => 'Assunto',
        'message' => 'Mensagem',
        'message_placeholder' => 'Conte o contexto em poucas linhas…',
        'submit' => 'Enviar mensagem',
        // Honeypot anti-spam (invisível para humanos — NÃO traduzir o name).
        'honeypot_label' => 'Website',
    ],

    'subjects' => [
        'suggestion' => 'Sugestão',
        'complaint' => 'Reclamação',
        'other' => 'Outro',
    ],

    'sent' => 'Mensagem enviada! Retornamos em breve no seu e-mail.',

    'mail' => [
        'subject_line' => ':platform — Contato: :subject',
        'intro' => 'Nova mensagem do formulário de contato da landing.',
        'from' => 'De',
        'subject_label' => 'Assunto',
    ],

];
