<?php

declare(strict_types=1);

// Autenticação (pt-BR): as mensagens que as regras do pacote twstec/kit-auth
// devolvem (recusas, avisos, política de senha) e os textos das telas de
// entrada, cadastro e verificação dos starters. O aplicativo vence: a mesma
// chave no lang/ dele prevalece sobre esta (ver docs/personalizacao.md).

return [

    'failed' => 'As credenciais informadas não conferem com nossos registros.',

    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

    'account_inactive' => 'Esta conta não está ativa. Fale com o suporte.',

    'registered' => 'Conta criada com sucesso. Bem-vindo(a)!',

    'logged_out' => 'Sessão encerrada com sucesso.',

    'session_ended' => 'Sua sessão foi encerrada porque a senha ou a verificação em duas etapas da conta mudou. Entre novamente.',

    'password_policy' => [
        'min' => 'mínimo de :min caracteres',
        'with' => ':min, com :rules',
        'separator' => ', ',
        'letters' => 'ao menos uma letra',
        'mixed_case' => 'maiúscula e minúscula',
        'numbers' => 'ao menos um número',
        'symbols' => 'ao menos um símbolo',
    ],

    'email_verification' => [
        'sent' => 'Enviamos um novo link de confirmação para o seu e-mail.',
        'registered' => 'Conta criada. Confirme o e-mail para liberar o painel.',
        'cooldown' => 'Aguarde :seconds segundos para pedir um novo envio.',
        'verified' => 'E-mail confirmado. Bem-vindo(a)!',
        'invalid_link' => 'Este link de confirmação é inválido ou expirou. Peça um novo envio abaixo.',
        'not_verified' => 'Confirme seu e-mail para continuar.',
        'wrong_account' => 'Este link é de outra conta. Saia e entre com a conta que recebeu o e-mail.',

        // Verificação de e-mail do cadastro (Twstec\Kit\Auth\Support\EmailVerification).
        'title' => 'Confirme seu e-mail',
        'intro' => 'Enviamos um link de confirmação para :email. Abra o e-mail e clique no link para liberar o painel.',
        'hint' => 'Não chegou? Confira o spam ou peça um novo envio.',
        'resend' => 'Reenviar e-mail',
    ],

    'password_confirmation' => [
        'locked' => 'Muitas tentativas com senha incorreta. Por segurança, aguarde :minutes minuto(s) e tente novamente.',
    ],

    'transaction_password' => [
        'invalid' => 'A senha de transação informada está incorreta.',
        'current_invalid' => 'A senha de transação atual está incorreta.',
        'same_as_login' => 'A senha de transação deve ser diferente da senha de login.',
        'saved' => 'Senha de transação salva com sucesso.',
    ],

    'verification_code' => [
        'sent' => 'Enviamos um código de verificação para o seu e-mail.',
        'invalid' => 'O código informado é inválido.',
        'expired' => 'O código expirou ou não existe. Solicite um novo.',
        'resend_cooldown' => 'Aguarde :seconds segundos para solicitar um novo código.',
    ],

    'two_factor' => [
        'code_label' => 'Código de verificação',
        'invalid' => 'Código incorreto. Confira o último e-mail recebido.',
        'expired' => 'Este código expirou ou já foi usado. Peça um novo abaixo.',
        'resend_cooldown' => 'Aguarde :seconds segundos para pedir outro código.',
        'resent' => 'Enviamos um novo código para o seu e-mail.',
        'cancelled' => 'Entrada cancelada. Nada foi autenticado.',
        'challenge_expired' => 'A verificação expirou. Entre novamente com sua senha.',
        'locked' => 'Muitos códigos incorretos. Por segurança, aguarde :minutes minuto(s) e entre novamente.',
        'unavailable' => 'A verificação em duas etapas não está disponível nesta instalação.',
        'account_protected' => 'Indisponível nesta conta: ela é protegida, e a verificação em duas etapas não pode ser ligada nela.',
        // Nome antigo (até a 2.x), com o mesmo texto: sai na 3.0.
        'demo_blocked' => 'Indisponível nesta conta: ela é protegida, e a verificação em duas etapas não pode ser ligada nela.',
        'requires_transaction_password' => 'Defina sua senha de transação antes: ligar e desligar a verificação em duas etapas são ações sensíveis.',
        'setup_required' => 'A verificação em duas etapas é obrigatória nesta instalação. Configure-a para continuar.',
        'required_cannot_disable' => 'A verificação em duas etapas é obrigatória nesta instalação e não pode ser desligada.',
        'setup_done' => 'Verificação em duas etapas ligada. A partir do próximo login, pediremos o código enviado ao seu e-mail.',

        // Verificação em duas etapas no LOGIN (TwoFactorLogin): tela do código,
        // mensagens do fluxo e as recusas de ligar/desligar.
        'title' => 'Verificação em duas etapas',
        'intro' => 'Enviamos um código de 6 dígitos para :email. Digite-o abaixo para concluir a entrada — ele vale por :minutes minutos.',
        'submit' => 'Confirmar e entrar',
        'resend' => 'Enviar outro código',
        'resend_hint' => 'Não chegou? Confira o spam. Um código novo invalida o anterior.',
        'cancel' => 'Voltar ao login',
        'enabled' => 'Verificação em duas etapas ligada. A partir do próximo login, pediremos o código enviado ao seu e-mail.',
        'disabled' => 'Verificação em duas etapas desligada. O login volta a pedir só a senha.',
    ],

    'sensitive_action' => [
        'token_issued' => 'Ação sensível autorizada. Use o token imediatamente — ele é de uso único.',
        'invalid_token' => 'Token de ação sensível ausente, inválido ou expirado. Confirme a ação novamente.',
    ],

    'password' => 'A senha informada está incorreta.',

    // Configuração do segundo fator OBRIGATÓRIO (AUTH_TWO_FACTOR_REQUIRED).
    'two_factor_setup' => [
        'title' => 'Configure a verificação em duas etapas',
        'intro' => 'Esta instalação exige a verificação em duas etapas. Depois de configurada, cada login pede, além da senha, um código enviado para :email.',
        'grace' => 'Você pode adiar até :date. Depois disso, o painel só abre com a verificação configurada.',
        'date_format' => 'd/m/Y',
        'transaction_password_heading' => 'Primeiro, defina sua senha de transação',
        'transaction_password_hint' => 'Ligar a verificação é uma ação sensível: ela é confirmada com a senha de transação (diferente da senha de login) e um código por e-mail.',
        'confirm_heading' => 'Confirme com sua senha de transação',
        'confirm_hint' => 'Enviaremos um código de 6 dígitos para o seu e-mail.',
        'send_code' => 'Enviar código',
        'code_intro' => 'Enviamos um código de 6 dígitos para :email. Digite-o abaixo — ele vale por :minutes minutos.',
        'submit' => 'Ligar e continuar',
        'resend_hint' => 'Não chegou? Confira o spam ou peça outro código com a senha de transação.',
        'later' => 'Configurar depois',
    ],

    // Strings de interface (formulários/telas de autenticação).
    'ui' => [
        'login_title' => 'Entrar',
        'login_submit' => 'Entrar',
        'login_link' => 'Já tem conta? Entrar',
        'register_title' => 'Criar conta',
        'register_submit' => 'Criar conta',
        'register_link' => 'Criar conta',
        'name' => 'Nome completo',
        'email' => 'E-mail',
        'password' => 'Senha',
        'new_password' => 'Nova senha',
        'password_confirmation' => 'Confirme a senha',
        'remember_me' => 'Manter conectado',
        'forgot_password' => 'Esqueci minha senha',
        'forgot_title' => 'Recuperar senha',
        'forgot_subtitle' => 'Informe seu e-mail para receber o link de redefinição.',
        'forgot_submit' => 'Enviar link de redefinição',
        'reset_title' => 'Redefinir senha',
        'reset_submit' => 'Redefinir senha',
        'logout' => 'Sair',
        'save' => 'Salvar',
        'transaction_password_title' => 'Senha de transação',
        'transaction_password_subtitle' => 'Usada para autorizar ações sensíveis (exclusão de conta, chaves de API). Deve ser diferente da senha de login.',
        'current_transaction_password' => 'Senha de transação atual',
        'new_transaction_password' => 'Nova senha de transação',
        'dashboard_title' => 'Painel',
        'dashboard_greeting' => 'Olá, :name',
        'dashboard_code' => 'Seu código de usuário',
    ],

];
