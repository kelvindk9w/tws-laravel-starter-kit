<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailBehavior;
use Twstec\Kit\Auth\Notifications\VerifyEmailNotification;
use Twstec\Kit\Auth\Support\ProtectedAccounts;

/**
 * Verificação de e-mail do cadastro (MustVerifyEmail do framework), com duas
 * diferenças: o e-mail sai no layout do kit, no idioma do DESTINATÁRIO, e a
 * conta protegida conta como verificada. A regra de quando a verificação é
 * exigida mora em Support\EmailVerification.
 */
trait VerifiesEmail
{
    use MustVerifyEmailBehavior;

    /**
     * O e-mail está confirmado?
     *
     * CONTA PROTEGIDA CONTA COMO VERIFICADA (ver ProtectedAccounts). Uma conta
     * protegida contra alteração pode ter e-mail fictício e senha pública —
     * se a verificação dependesse só da coluna, bastaria alguém zerar
     * `email_verified_at` (campo que a proteção deixa livre) para o próximo
     * acesso cair na tela de aviso esperando um e-mail que ninguém recebe.
     * Vale só enquanto a proteção vale; fora dela a conta é uma conta comum.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null || ProtectedAccounts::protects($this);
    }

    /**
     * E-mail de verificação do cadastro, no layout do kit e no idioma do
     * DESTINATÁRIO (notificação enfileirada com payload criptografado).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
