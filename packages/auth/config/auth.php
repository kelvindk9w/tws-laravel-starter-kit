<?php

declare(strict_types=1);

// =============================================================================
// Configuração padrão do twstec/kit-auth — as chaves do KIT dentro de
// config('auth'), ao lado das do framework (guards, providers, passwords).
//
// O pacote junta este arquivo ao config/auth.php da aplicação
// (mergeConfigFrom): cada chave de primeiro nível que a aplicação declarar
// prevalece inteira; as que ela não declarar vêm daqui. O starter mantém a
// cópia completa no config/auth.php dele.
//
// PROTEÇÕES QUE O PACOTE LIGA SOZINHO (ver AuthServiceProvider):
//   - status da conta a cada requisição web (EnsureAccountIsActive no grupo
//     `web`);
//   - os aliases `verified` (e-mail confirmado, com a regra do kit) e
//     `sensitive.token` (token de ação sensível de uso único).
// Opt-out só explícito, e com aviso no log a cada boot:
//   AUTH_WEB_PROTECTIONS=false (auth.web_protections.enabled)
// =============================================================================

return [

    // Proteções do grupo `web` e aliases de middleware instalados pelo pacote.
    // Desligar só faz sentido se a aplicação instalar as mesmas proteções por
    // conta própria — o pacote avisa no log a cada boot.
    'web_protections' => [
        'enabled' => (bool) env('AUTH_WEB_PROTECTIONS', true),
    ],

    // Política da senha de LOGIN — consumida por Twstec\Kit\Auth\PasswordPolicy
    // em TODOS os pontos (registro, reset, perfil, /admin). O kit nasce com o
    // mínimo (só o tamanho); cada exigência extra é um toggle no .env:
    //   AUTH_PASSWORD_LETTERS=true        pelo menos uma letra
    //   AUTH_PASSWORD_MIXED_CASE=true     maiúscula E minúscula
    //   AUTH_PASSWORD_NUMBERS=true        pelo menos um número
    //   AUTH_PASSWORD_SYMBOLS=true        pelo menos um símbolo
    //   AUTH_PASSWORD_UNCOMPROMISED=true  recusa senha vazada (consulta a API
    //                                     Have I Been Pwned por k-anonimato —
    //                                     exige saída de rede no servidor)
    // As dicas dos formulários são montadas a partir do que está ativo, e as
    // mensagens de erro já existem nos 3 idiomas (lang/*/validation.php).
    'password_rules' => [
        'min_length' => (int) env('AUTH_PASSWORD_MIN', 6),
        'letters' => (bool) env('AUTH_PASSWORD_LETTERS', false),
        'mixed_case' => (bool) env('AUTH_PASSWORD_MIXED_CASE', false),
        'numbers' => (bool) env('AUTH_PASSWORD_NUMBERS', false),
        'symbols' => (bool) env('AUTH_PASSWORD_SYMBOLS', false),
        'uncompromised' => (bool) env('AUTH_PASSWORD_UNCOMPROMISED', false),
    ],

    // Bloqueio por tentativas de login (throttle + contador).
    'login' => [
        // Tentativas consecutivas antes do bloqueio.
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        // Duração do bloqueio (decay do rate limiter), em minutos.
        'lockout_minutes' => (int) env('AUTH_LOGIN_LOCKOUT_MINUTES', 15),
    ],

    // Senha de TRANSAÇÃO (separada da senha de login).
    'transaction_password' => [
        'min_length' => (int) env('AUTH_TRANSACTION_PASSWORD_MIN', 8),
    ],

    // Código de verificação (2FA por e-mail; canais futuros:
    // TOTP/WhatsApp via drivers de VerificationChannel).
    'verification' => [
        // Canal padrão de envio do código.
        'default_channel' => env('AUTH_VERIFICATION_CHANNEL', 'email'),
        // Validade do código, em minutos.
        'code_ttl_minutes' => (int) env('AUTH_VERIFICATION_CODE_TTL_MINUTES', 10),
        // Máximo de tentativas de confirmação antes de invalidar o código.
        'max_attempts' => (int) env('AUTH_VERIFICATION_CODE_MAX_ATTEMPTS', 5),
        // Intervalo mínimo entre reenvios, em segundos (cooldown).
        'resend_cooldown_seconds' => (int) env('AUTH_VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS', 60),
    ],

    // Verificação de e-mail no cadastro (Twstec\Kit\Auth\Support\EmailVerification).
    // LIGADA por padrão: conta que não confirmou o e-mail não entra no painel
    // (páginas, formulários e ações Livewire) nem usa a API — só vê o aviso com
    // "reenviar" e "sair". Desligar (AUTH_EMAIL_VERIFICATION_REQUIRED=false) é
    // para projetos que não querem a etapa: o cadastro volta a ir direto ao
    // painel e nenhum e-mail de verificação é enviado. Ver docs/autenticacao.md.
    'email_verification' => [
        'required' => (bool) env('AUTH_EMAIL_VERIFICATION_REQUIRED', true),
        // Validade do link assinado do e-mail, em minutos.
        'link_ttl_minutes' => (int) env('AUTH_EMAIL_VERIFICATION_LINK_TTL_MINUTES', 60),
        // Intervalo mínimo entre dois envios do e-mail para a mesma conta,
        // em segundos (o envio do cadastro também conta).
        'resend_cooldown_seconds' => (int) env('AUTH_EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS', 60),
    ],

    // Token de ação sensível: emitido após senha de transação + código válido;
    // curta duração e USO ÚNICO.
    'sensitive_action' => [
        'token_ttl_minutes' => (int) env('AUTH_SENSITIVE_TOKEN_TTL_MINUTES', 10),
    ],

    // Verificação em duas etapas no LOGIN (Twstec\Kit\Auth\Services\TwoFactorLogin).
    // Opcional, por conta: quem liga no próprio perfil (/profile ou
    // /admin/profile) passa a receber um código por e-mail depois da senha. O
    // código é o do motor comum (seção `verification` acima: validade,
    // tentativas por código e intervalo de reenvio). Ver docs/autenticacao.md.
    'two_factor' => [
        // A opção existe nesta instalação? Desligar ESCONDE a opção dos perfis
        // e faz o login ignorar a preferência de quem já tinha ligado (que
        // fica gravada e volta a valer se a flag for religada).
        'enabled' => (bool) env('AUTH_TWO_FACTOR_ENABLED', true),
        // Validade do estado intermediário (senha certa, código pendente), em
        // minutos. Vencido, a pessoa volta ao login e digita a senha de novo.
        'challenge_ttl_minutes' => (int) env('AUTH_TWO_FACTOR_CHALLENGE_TTL_MINUTES', 10),
        // Códigos errados aceitos por CONTA e por IP (somando todos os
        // códigos da janela) antes do bloqueio — o limite por código sozinho
        // deixaria pedir código novo e seguir tentando.
        'max_attempts_per_account' => (int) env('AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_ACCOUNT', 10),
        'max_attempts_per_ip' => (int) env('AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_IP', 30),
        // Duração do bloqueio (janela dos dois contadores acima), em minutos.
        'lockout_minutes' => (int) env('AUTH_TWO_FACTOR_LOCKOUT_MINUTES', 15),
    ],

];
