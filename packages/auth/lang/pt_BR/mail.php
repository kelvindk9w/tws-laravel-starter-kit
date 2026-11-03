<?php

declare(strict_types=1);

// E-mails de autenticação (pt-BR): os assuntos, montados pelas classes de
// e-mail do pacote twstec/kit-auth, e os textos do corpo
// (resources/views/mail/messages do pacote). O aplicativo vence: a mesma chave
// no lang/ dele prevalece (ver docs/personalizacao.md).

return [

    'verification_code' => [
        'subject' => ':platform — Seu código de verificação',

        // Código de verificação (2FA por e-mail).
        'preheader' => 'Seu código expira em :minutes minutos.',
        'heading' => 'Seu código de verificação',
        'intro' => 'Use o código abaixo para confirmar a ação solicitada. Ele é de uso único.',
        'expires' => 'Este código expira em :minutes minutos.',
        'ignore' => 'Se você não solicitou esta ação, ignore este e-mail e considere trocar sua senha.',
    ],

    'login_code' => [
        'subject' => ':platform — Seu código de acesso',

        // Código do segundo fator do LOGIN (mesmo e-mail do código, outra finalidade).
        'preheader' => 'Seu código de acesso expira em :minutes minutos.',
        'heading' => 'Seu código de acesso',
        'intro' => 'A senha da sua conta foi digitada corretamente e falta um passo para concluir a entrada. Se foi você, use o código abaixo. Ele é de uso único.',
        'expires' => 'Este código expira em :minutes minutos.',
        'ignore' => 'Não foi você? Não repasse este código a ninguém e troque sua senha agora: quem tentou entrar sabe a senha atual.',
    ],

    'email_verification' => [
        'subject' => ':platform — Confirme seu e-mail',

        // Recuperação de senha (bug de QA #9 — antes vinha em inglês do pacote).
        'preheader' => 'Falta um passo: confirme o e-mail para liberar sua conta.',
        'heading' => 'Confirme seu e-mail',
        'intro' => 'Sua conta foi criada. Para liberar o acesso ao painel, confirme que este endereço é seu.',
        'action' => 'Confirmar e-mail',
        'expires' => 'Este link expira em :minutes minutos. Depois disso, peça um novo na tela de aviso.',
        'fallback' => 'Se o botão não funcionar, copie e cole este endereço no navegador:',
        'ignore' => 'Se você não criou esta conta, ignore este e-mail: sem a confirmação, ela não é liberada.',
    ],

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

];
