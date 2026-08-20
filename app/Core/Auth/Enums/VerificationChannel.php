<?php

declare(strict_types=1);

namespace App\Core\Auth\Enums;

/**
 * Canal de envio/verificação de códigos (2FA). Hoje: e-mail.
 * TOTP (app autenticador) e WhatsApp entram como novos cases + drivers
 * (ADR-006: "Futuro: app autenticador e/ou WhatsApp").
 */
enum VerificationChannel: string
{
    case Email = 'email';
    // Futuros: case Totp = 'totp'; case WhatsApp = 'whatsapp';
}
