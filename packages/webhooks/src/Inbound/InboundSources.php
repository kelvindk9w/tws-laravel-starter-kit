<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

/**
 * O REGISTRO das fontes de webhooks recebidos: as registradas pelo código
 * (InboundWebhooks::register — vencem) e as da configuração
 * (`webhooks.inbound_sources`), montadas na hora em que são pedidas (a
 * configuração de cada requisição vale; segredo nenhum fica guardado aqui
 * além do que o código registrou).
 *
 * Singleton (WebhooksServiceProvider). Fonte desconhecida = null (404 na
 * rota). Fonte mal configurada lança InvalidSourceConfiguration (503).
 */
final class InboundSources
{
    /**
     * @var array<string, InboundSource>
     */
    private array $registered = [];

    public function register(InboundSource $source): void
    {
        $this->registered[$source->name] = $source;
    }

    public function forget(string $name): void
    {
        unset($this->registered[$name]);
    }

    /**
     * @throws InvalidSourceConfiguration
     */
    public function find(string $name): ?InboundSource
    {
        if (isset($this->registered[$name])) {
            return $this->registered[$name];
        }

        if (preg_match(InboundSource::NAME_PATTERN, $name) !== 1) {
            return null;
        }

        $config = config('webhooks.inbound_sources.'.$name);

        if (! is_array($config)) {
            return null;
        }

        /** @var array<string, mixed> $config */
        return InboundSource::fromConfig($name, $config);
    }

    /**
     * Requisições por minuto da fonte, por IP — sem montar o verificador (o
     * limitador roda antes do recebimento). Desconhecida: o padrão.
     */
    public function rateLimitFor(string $name): int
    {
        if (isset($this->registered[$name])) {
            return $this->registered[$name]->rateLimitPerMinute();
        }

        $configured = preg_match(InboundSource::NAME_PATTERN, $name) === 1
            ? config('webhooks.inbound_sources.'.$name.'.rate_limit_per_minute')
            : null;

        return max(1, is_numeric($configured) ? (int) $configured : (int) config('webhooks.inbound.rate_limit_per_minute', 600));
    }

    /**
     * Os nomes de todas as fontes conhecidas.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $configured = config('webhooks.inbound_sources', []);
        $names = [...array_keys($this->registered), ...(is_array($configured) ? array_map('strval', array_keys($configured)) : [])];

        return array_values(array_unique($names));
    }
}
