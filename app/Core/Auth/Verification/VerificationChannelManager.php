<?php

declare(strict_types=1);

namespace App\Core\Auth\Verification;

use App\Core\Auth\Contracts\VerificationChannelDriver;
use App\Core\Auth\Enums\VerificationChannel;
use App\Core\Auth\Verification\Drivers\EmailVerificationDriver;
use InvalidArgumentException;

/**
 * Resolve o driver de canal de verificação (2FA).
 *
 * Ponto único de extensão: adicionar TOTP/WhatsApp = registrar um novo driver
 * no mapa abaixo (e o case correspondente no enum VerificationChannel).
 */
final class VerificationChannelManager
{
    /**
     * Mapa canal → driver.
     *
     * @var array<string, class-string<VerificationChannelDriver>>
     */
    private const DRIVERS = [
        'email' => EmailVerificationDriver::class,
        // Futuros: 'totp' => TotpVerificationDriver::class,
        //          'whatsapp' => WhatsAppVerificationDriver::class,
    ];

    /**
     * Canal padrão da plataforma (config auth.verification.default_channel).
     */
    public function defaultChannel(): VerificationChannel
    {
        return VerificationChannel::from((string) config('auth.verification.default_channel', 'email'));
    }

    public function driver(?VerificationChannel $channel = null): VerificationChannelDriver
    {
        $channel ??= $this->defaultChannel();

        $driverClass = self::DRIVERS[$channel->value] ?? null;

        if ($driverClass === null) {
            throw new InvalidArgumentException("Canal de verificação não suportado: {$channel->value}");
        }

        return app($driverClass);
    }
}
