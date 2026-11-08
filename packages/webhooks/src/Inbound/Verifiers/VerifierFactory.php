<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use Twstec\Kit\Webhooks\Inbound\Contracts\Verifier;
use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;

/**
 * Monta o verificador de uma fonte a partir da configuração (`verifier` da
 * fonte): um driver do pacote ou o nome de uma classe do aplicativo que
 * implementa Contracts\Verifier.
 */
final class VerifierFactory
{
    /**
     * @var array<string, class-string<Verifier>>
     */
    public const DRIVERS = [
        'header_token' => SharedSecretHeader::class,
        'hmac' => HmacSignature::class,
        'standard_webhooks' => StandardWebhooks::class,
        'public_key' => PublicKeySignature::class,
    ];

    /**
     * @param  array<string, mixed>  $options
     */
    public static function make(array $options): Verifier
    {
        $driver = $options['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidSourceConfiguration('verifier.driver ausente: use header_token, hmac, standard_webhooks, public_key ou uma classe que implemente '.Verifier::class.'.');
        }

        $class = self::DRIVERS[$driver] ?? $driver;

        if (! class_exists($class) || ! is_subclass_of($class, Verifier::class)) {
            throw new InvalidSourceConfiguration("verifier.driver desconhecido: {$driver}.");
        }

        if (method_exists($class, 'fromConfig')) {
            $verifier = $class::fromConfig($options);
        } else {
            $verifier = app()->make($class);
        }

        if (! $verifier instanceof Verifier) {
            throw new InvalidSourceConfiguration("verifier.driver {$driver} não devolveu um verificador.");
        }

        return $verifier;
    }
}
