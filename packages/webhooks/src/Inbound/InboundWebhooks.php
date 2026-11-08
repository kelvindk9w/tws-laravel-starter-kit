<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound;

/**
 * A porta de entrada do APLICATIVO para os webhooks RECEBIDOS.
 *
 * O caminho comum é a configuração (`webhooks.inbound_sources` no
 * config/webhooks.php do aplicativo — ver docs/webhooks-entrada.md). Para
 * montar uma fonte pelo código (verificador próprio, segredo de outro
 * lugar), registre num provider — o worker da fila também sobe os
 * providers, então o registro vale nos dois lados:
 *
 *     InboundWebhooks::register(new InboundSource(name: 'pagamentos', verifier: ..., handler: ...));
 *
 * A URL de cada fonte é `POST /<webhooks.inbound.path>/<nome>` (padrão
 * `/webhooks/inbound/<nome>`).
 */
final class InboundWebhooks
{
    public static function register(InboundSource $source): void
    {
        app(InboundSources::class)->register($source);
    }

    /**
     * @throws InvalidSourceConfiguration
     */
    public static function source(string $name): ?InboundSource
    {
        return app(InboundSources::class)->find($name);
    }

    /**
     * O caminho (sem barra nas pontas) sob o qual as fontes respondem.
     */
    public static function path(): string
    {
        return trim(trim((string) config('webhooks.inbound.path', 'webhooks/inbound')), '/');
    }
}
