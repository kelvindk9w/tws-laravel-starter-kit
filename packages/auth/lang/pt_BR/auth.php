<?php

declare(strict_types=1);

// Mensagens do domínio de autenticação (pt-BR) — as que as regras do pacote
// twstec/kit-auth devolvem (recusas, avisos, política de senha). Os textos das
// TELAS (títulos, rótulos, botões) são do front. O aplicativo vence: a mesma
// chave no lang/ dele prevalece sobre esta.

return [

    'failed' => 'As credenciais informadas não conferem com nossos registros.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',
    'account_inactive' => 'Esta conta não está ativa. Fale com o suporte.',
    'registered' => 'Conta criada com sucesso. Bem-vindo(a)!',
    'logged_out' => 'Sessão encerrada com sucesso.',
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
        'demo_blocked' => 'Indisponível na conta de demonstração: ligar a verificação em duas etapas trancaria a demo para os próximos visitantes.',
        'requires_transaction_password' => 'Defina sua senha de transação antes: ligar e desligar a verificação em duas etapas são ações sensíveis.',
    ],
    'sensitive_action' => [
        'token_issued' => 'Ação sensível autorizada. Use o token imediatamente — ele é de uso único.',
        'invalid_token' => 'Token de ação sensível ausente, inválido ou expirado. Confirme a ação novamente.',
    ],

];
