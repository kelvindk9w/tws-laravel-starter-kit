<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

use Twstec\Kit\Auth\Notifications\ResetPasswordNotification;

/**
 * E-mail de recuperação de senha no layout do kit e no idioma do
 * DESTINATÁRIO. A notificação nativa do Laravel usa linhas em inglês; esta é
 * traduzida (chaves mail.password_reset.*) e enfileirada como os demais.
 */
trait SendsPasswordResetNotification
{
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
