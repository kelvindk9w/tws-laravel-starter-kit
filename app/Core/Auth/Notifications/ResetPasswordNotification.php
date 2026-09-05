<?php

declare(strict_types=1);

namespace App\Core\Auth\Notifications;

use App\Core\Mail\KitMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail de recuperação de senha (bug de QA #9).
 *
 * A notificação nativa do Laravel monta o texto com as linhas em inglês do
 * pacote (que não existem no lang/ do projeto), então o e-mail chegava em
 * inglês mesmo com pt-BR ativo — enquanto o código 2FA, que é um Mailable
 * com ->locale($user->preferredLocale()), chegava certo.
 *
 * Aqui as linhas vêm das chaves mail.password_reset.* e o idioma é o do
 * DESTINATÁRIO: o User implementa HasLocalePreference e o Laravel usa essa
 * preferência ao enfileirar/enviar (o e-mail sai no idioma da conta,
 * não no de quem estiver navegando no servidor).
 *
 * O corpo é o layout ÚNICO do kit (KitMailMessage → <x-email::layouts.kit>),
 * o mesmo dos Mailables — o padrão ->line()/->action() do framework fazia
 * esta mensagem chegar com outra identidade visual que as demais.
 *
 * SEMPRE enfileirada (mesma política do VerificationCodeMail).
 */
final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return KitMailMessage::make(
            __('mail.password_reset.subject', ['platform' => platform()->name]),
            'mail.messages.password-reset',
            [
                'resetUrl' => $this->resetUrl($notifiable),
                'expiresInMinutes' => (int) config('auth.passwords.users.expire', 60),
            ],
        );
    }

    /**
     * URL da tela de redefinição (rota nomeada — nada de caminho fixo).
     */
    private function resetUrl(object $notifiable): string
    {
        return route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
