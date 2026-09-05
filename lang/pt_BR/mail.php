<?php

declare(strict_types=1);

// Strings de e-mails transacionais (pt-BR). Toda string passa por __() — ADR-007.
// O corpo dos e-mails vive em resources/views/mail/messages/**, sobre o layout
// único <x-email::layouts.kit>. Ver README, seção "E-mails transacionais".

return [

    // Rodapé COMUM a todos os e-mails (o layout monta o resto com platform()).
    'footer' => [
        'transactional' => 'Este é um e-mail automático relacionado à sua conta — não é divulgação, e por isso não tem link de descadastro.',
        'rights' => '© :year :company. Todos os direitos reservados.',
        'cnpj' => 'CNPJ',
    ],

    // Aviso prévio de expiração de chave de API por inatividade (ADR-006).
    'api_key_inactivity' => [
        'subject' => ':platform — Sua chave de API será desativada por inatividade',
        'preheader' => 'Uma chave sem uso será desativada em :days dias.',
        'heading' => 'Uma chave de API sua está prestes a ser desativada',
        'intro' => 'A chave abaixo está sem uso e será desativada automaticamente por inatividade.',
        'name_label' => 'Nome da chave',
        'code_label' => 'Código público',
        'key_label' => 'Chave pública',
        'expires' => 'A desativação acontece em :days dias.',
        'action' => 'Para mantê-la ativa, basta fazer uma requisição autenticada com ela. Se não precisar mais dela, recomendamos revogá-la no painel.',
        'cta' => 'Abrir minhas chaves',
        'ignore' => 'Se você não reconhece esta chave, revogue-a imediatamente e troque suas credenciais.',
    ],

    // Código de verificação (2FA por e-mail — ADR-006).
    'verification_code' => [
        'subject' => ':platform — Seu código de verificação',
        'preheader' => 'Seu código expira em :minutes minutos.',
        'heading' => 'Seu código de verificação',
        'intro' => 'Use o código abaixo para confirmar a ação solicitada. Ele é de uso único.',
        'expires' => 'Este código expira em :minutes minutos.',
        'ignore' => 'Se você não solicitou esta ação, ignore este e-mail e considere trocar sua senha.',
    ],

    // Recuperação de senha (bug de QA #9 — antes vinha em inglês do pacote).
    'password_reset' => [
        'subject' => ':platform — Redefinição de senha',
        'preheader' => 'Link de redefinição válido por :minutes minutos.',
        'heading' => 'Redefinir sua senha',
        'intro' => 'Você está recebendo este e-mail porque recebemos um pedido de redefinição de senha para a sua conta.',
        'action' => 'Redefinir senha',
        'expires' => 'Este link expira em :minutes minutos.',
        'fallback' => 'Se o botão não funcionar, copie e cole este endereço no navegador:',
        'ignore' => 'Se você não pediu a redefinição, nenhuma ação é necessária.',
    ],

    // Mensagem do formulário de contato da landing → e-mail do time.
    // O assunto continua em contact.mail.subject_line (é a string do módulo
    // de contato); aqui ficam só as partes do CORPO no layout do kit.
    'contact_message' => [
        'preheader' => 'Nova mensagem de :name (:subject).',
        'heading' => 'Nova mensagem do formulário de contato',
        'message_label' => 'Mensagem',
        'reply_hint' => 'Responder este e-mail responde direto para quem escreveu.',
    ],

    // Tela de pré-visualização dos e-mails (/mail-preview) — só em dev.
    'preview' => [
        'title' => 'Pré-visualização dos e-mails',
        'subtitle' => 'Todos os e-mails transacionais do kit com dados de exemplo, nos três idiomas e nos dois temas. Ferramenta de desenvolvimento: em produção esta rota responde 404.',
        'list_heading' => 'E-mails',
        'language' => 'Idioma',
        'scheme' => 'Tema',
        'subject' => 'Assunto',
        'plain_text' => 'Versão em texto puro',
        'open_html' => 'Abrir o HTML',
        'open_text' => 'Ver o texto puro',
        'mailpit_hint' => 'Para conferir como o e-mail chega de verdade (cabeçalhos, multipart, anexos), dispare o fluxo e abra o Mailpit em http://localhost:18025.',
        'emails' => [
            'verification-code' => 'Código de verificação',
            'password-reset' => 'Redefinição de senha',
            'api-key-inactivity' => 'Chave de API inativa',
            'contact-message' => 'Formulário de contato',
        ],
        'locales' => ['pt_BR' => 'Português', 'en' => 'English', 'es' => 'Español'],
        'schemes' => ['light' => 'Claro', 'dark' => 'Escuro'],
    ],

];
