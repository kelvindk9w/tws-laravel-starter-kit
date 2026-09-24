<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts;

use Twstec\Kit\Auth\Enums\VerificationChannel;
use Twstec\Kit\Auth\Enums\VerificationPurpose;

/**
 * Driver de canal de verificação (2FA) — contrato plugável.
 *
 * Hoje existe apenas o driver de e-mail. TOTP (app autenticador) e WhatsApp
 * entram como NOVOS drivers implementando esta interface, sem tocar no fluxo
 * de verificação.
 */
interface VerificationChannelDriver
{
    /**
     * Canal atendido por este driver.
     */
    public function channel(): VerificationChannel;

    /**
     * Envia o código de verificação ao usuário pelo canal.
     *
     * O envio DEVE ser assíncrono (queue) sempre que o canal envolver I/O
     * externo — nunca bloquear a requisição do usuário.
     */
    public function send(AuthUser $user, string $code, VerificationPurpose $purpose): void;
}
