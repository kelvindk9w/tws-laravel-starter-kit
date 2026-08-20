<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Acesso tipado à configuração centralizada da plataforma (ADR-007).
 *
 * Uso: platform()->name, platform()->supportEmail etc.
 * Nunca ler config('platform.*') espalhado pelo código — sempre por aqui,
 * para manter o contrato tipado e um único ponto de evolução.
 */
final readonly class Platform
{
    public function __construct(
        public string $name,
        public ?string $logoUrl,
        public string $officialUrl,
        public ?string $supportEmail,
        public ?string $cnpj,
        public string $locale,
        public string $displayTimezone,
        public string $currency,
    ) {}

    /**
     * Monta a instância a partir da config (config/platform.php ← .env).
     */
    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $config */
        $config = config('platform');

        return new self(
            name: (string) $config['name'],
            logoUrl: $config['logo_url'] !== null ? (string) $config['logo_url'] : null,
            officialUrl: (string) $config['official_url'],
            supportEmail: $config['support_email'] !== null ? (string) $config['support_email'] : null,
            cnpj: $config['cnpj'] !== null ? (string) $config['cnpj'] : null,
            locale: (string) $config['locale'],
            displayTimezone: (string) $config['display_timezone'],
            currency: (string) $config['currency'],
        );
    }
}
