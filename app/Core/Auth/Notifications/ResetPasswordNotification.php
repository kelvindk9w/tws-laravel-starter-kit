<?php

declare(strict_types=1);

namespace App\Core\Auth\Notifications;

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
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('mail.password_reset.subject', ['platform' => platform()->name]))
            ->line(__('mail.password_reset.intro'))
            ->action(__('mail.password_reset.action'), $this->resetUrl($notifiable))
            ->line(__('mail.password_reset.expires', ['minutes' => $minutes]))
            ->line(__('mail.password_reset.ignore'));
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
