<?php

declare(strict_types=1);

// Strings de autenticação (pt-BR). Toda string de UI passa por __().

return [

    'failed' => 'As credenciais informadas não conferem com nossos registros.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

    // Dica da política de senha de login, montada por PasswordPolicy::hint()
    // só com as regras ATIVAS (config/auth.php → password_rules).
    'password_policy' => [
        'min' => 'mínimo de :min caracteres',
        'with' => ':min, com :rules',
        'separator' => ', ',
        'letters' => 'ao menos uma letra',
        'mixed_case' => 'maiúscula e minúscula',
        'numbers' => 'ao menos um número',
        'symbols' => 'ao menos um símbolo',
    ],

    'account_inactive' => 'Esta conta não está ativa. Fale com o suporte.',
    'registered' => 'Conta criada com sucesso. Bem-vindo(a)!',
    'logged_out' => 'Sessão encerrada com sucesso.',

    // Verificação de e-mail do cadastro (App\Core\Auth\Support\EmailVerification).
    'email_verification' => [
        'title' => 'Confirme seu e-mail',
        'intro' => 'Enviamos um link de confirmação para :email. Abra o e-mail e clique no link para liberar o painel.',
        'hint' => 'Não chegou? Confira o spam ou peça um novo envio.',
        'resend' => 'Reenviar e-mail',
        'sent' => 'Enviamos um novo link de confirmação para o seu e-mail.',
        'registered' => 'Conta criada. Confirme o e-mail para liberar o painel.',
        'cooldown' => 'Aguarde :seconds segundos para pedir um novo envio.',
        'verified' => 'E-mail confirmado. Bem-vindo(a)!',
        'invalid_link' => 'Este link de confirmação é inválido ou expirou. Peça um novo envio abaixo.',
        'not_verified' => 'Confirme seu e-mail para continuar.',
        'wrong_account' => 'Este link é de outra conta. Saia e entre com a conta que recebeu o e-mail.',
    ],

    // Senha de transação (separada da senha de login).
    'transaction_password' => [
        'invalid' => 'A senha de transação informada está incorreta.',
        'current_invalid' => 'A senha de transação atual está incorreta.',
        'same_as_login' => 'A senha de transação deve ser diferente da senha de login.',
        'saved' => 'Senha de transação salva com sucesso.',
    ],

    // Código de verificação (2FA por e-mail).
    'verification_code' => [
        'sent' => 'Enviamos um código de verificação para o seu e-mail.',
        'invalid' => 'O código informado é inválido.',
        'expired' => 'O código expirou ou não existe. Solicite um novo.',
        'resend_cooldown' => 'Aguarde :seconds segundos para solicitar um novo código.',
    ],

    // Token de ação sensível (uso único, curta duração).
    'sensitive_action' => [
        'token_issued' => 'Ação sensível autorizada. Use o token imediatamente — ele é de uso único.',
        'invalid_token' => 'Token de ação sensível ausente, inválido ou expirado. Confirme a ação novamente.',
    ],

    // Strings de interface (formulários/telas de autenticação).
    // Blindagem das contas de demonstração (DemoAccountGuard): mensagens de
    // quem tentou mexer nelas fora da UI do super admin — tinker, comando
    // artisan, job. Ver docs/demo.md, "Contas demo são intocáveis".
    'demo_account' => [
        'update_blocked' => 'Conta de demonstração protegida: ":email" não aceita alteração de :fields. Nome, foto, idioma e tema continuam liberados.',
        'delete_blocked' => 'Conta de demonstração protegida: ":email" não pode ser excluída.',
    ],

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
        'transaction_password_subtitle' => 'Usada para autorizar ações sensíveis (saques, chaves de API). Deve ser diferente da senha de login.',
        'current_transaction_password' => 'Senha de transação atual',
        'new_transaction_password' => 'Nova senha de transação',
        'dashboard_title' => 'Painel',
        'dashboard_greeting' => 'Olá, :name',
        'dashboard_code' => 'Seu código de usuário',

        // Login demo: só quando config('ui.demo_login.enabled') — local/dev.
        'demo_notice' => 'Ambiente de demonstração: as credenciais abaixo já vêm preenchidas, basta entrar.',
        'demo_credentials' => 'Usuário demo',
    ],

];
