<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Support\EmailVerification;
use Twstec\Kit\Foundation\Mail\KitMailMessage;

/**
 * E-mail de verificação do cadastro.
 *
 * Substitui a notificação nativa do Laravel pelo mesmo motivo da recuperação
 * de senha: a nativa vem em inglês, com o visual do framework, e monta o link
 * com `route()` — que dentro de uma requisição usa o `Host` recebido. Aqui o
 * corpo é o layout único do kit (KitMailMessage), as linhas vêm de
 * `mail.email_verification.*` no idioma do DESTINATÁRIO (User implementa
 * HasLocalePreference) e o link sai de EmailVerification::verificationUrl(),
 * ancorado em APP_URL.
 *
 * O link é montado no momento do ENVIO (no worker), então a validade conta a
 * partir de quando o e-mail sai, não de quando foi enfileirado. O payload do
 * job é CRIPTOGRAFADO (ShouldBeEncrypted), como todo e-mail do kit.
 */
final class VerifyEmailNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AuthUser $notifiable): MailMessage
    {
        return KitMailMessage::make(
            __('mail.email_verification.subject', ['platform' => platform()->name]),
            'mail.messages.email-verification',
            [
                'verificationUrl' => EmailVerification::verificationUrl($notifiable),
                'expiresInMinutes' => EmailVerification::linkTtlMinutes(),
            ],
        );
    }
}
