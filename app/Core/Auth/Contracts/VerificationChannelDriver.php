<?php

declare(strict_types=1);

namespace App\Core\Auth\Contracts;

use App\Core\Auth\Enums\VerificationChannel;
use App\Core\Auth\Enums\VerificationPurpose;
use App\Core\Auth\Models\User;

/**
 * Driver de canal de verificação (2FA) — contrato plugável.
 *
 * Hoje existe apenas o driver de e-mail. TOTP (app autenticador) e WhatsApp
 * entram como NOVOS drivers implementando esta interface, sem tocar no fluxo
 * de verificação (ADR-006: "Futuro: app autenticador e/ou WhatsApp").
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
    public function send(User $user, string $code, VerificationPurpose $purpose): void;
}
