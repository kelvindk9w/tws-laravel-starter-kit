<?php

declare(strict_types=1);

// Strings de autenticação (pt-BR). Toda string de UI passa por __() — ADR-007.

return [

    'failed' => 'As credenciais informadas não conferem com nossos registros.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

    'account_inactive' => 'Esta conta não está ativa. Fale com o suporte.',
    'registered' => 'Conta criada com sucesso. Bem-vindo(a)!',
    'logged_out' => 'Sessão encerrada com sucesso.',

    // Senha de transação (ADR-006 — separada da senha de login).
    'transaction_password' => [
        'invalid' => 'A senha de transação informada está incorreta.',
        'current_invalid' => 'A senha de transação atual está incorreta.',
        'same_as_login' => 'A senha de transação deve ser diferente da senha de login.',
        'saved' => 'Senha de transação salva com sucesso.',
    ],

    // Código de verificação (2FA por e-mail — checklist item 24).
    'verification_code' => [
        'sent' => 'Enviamos um código de verificação para o seu e-mail.',
        'invalid' => 'O código informado é inválido.',
        'expired' => 'O código expirou ou não existe. Solicite um novo.',
        'resend_cooldown' => 'Aguarde :seconds segundos para solicitar um novo código.',
    ],

    // Token de ação sensível (uso único, curta duração — ADR-006/010).
    'sensitive_action' => [
        'token_issued' => 'Ação sensível autorizada. Use o token imediatamente — ele é de uso único.',
        'invalid_token' => 'Token de ação sensível ausente, inválido ou expirado. Confirme a ação novamente.',
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
