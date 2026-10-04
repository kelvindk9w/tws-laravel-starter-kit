<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Signing;

/**
 * A ASSINATURA de um webhook — o que o receptor confere.
 *
 * Conteúdo assinado: `"{timestamp}.{corpo}"` — o carimbo de tempo (segundos
 * Unix, o mesmo do cabeçalho), um ponto e o CORPO BRUTO exatamente como foi
 * enviado (bytes, antes de qualquer decodificação de JSON).
 * Algoritmo: HMAC-SHA256 com o segredo do endpoint, em hexadecimal minúsculo.
 *
 * Cabeçalho `X-Webhook-Signature: t=<timestamp>,v1=<hex>` — durante a
 * convivência depois de uma rotação, um `v1=` para cada segredo vigente
 * (`t=…,v1=<novo>,v1=<anterior>`): o receptor aceita se QUALQUER um conferir.
 *
 * Os outros cabeçalhos (`X-Webhook-Id`, o id do evento para deduplicar, e
 * `X-Webhook-Event`, o tipo) são informativos: quem garante integridade é a
 * assinatura sobre o corpo, que também traz o id e o tipo.
 *
 * O exemplo de verificação (tolerância de tempo + comparação em tempo
 * constante) está em examples/verify-signature.php e em docs/webhooks.md —
 * e é o MESMO código que a suíte usa no receptor de teste.
 */
final class Signature
{
    public const HEADER = 'X-Webhook-Signature';

    public const ID_HEADER = 'X-Webhook-Id';

    public const EVENT_HEADER = 'X-Webhook-Event';

    public const VERSION = 'v1';

    /**
     * HMAC-SHA256 de "{timestamp}.{corpo}" com o segredo, em hex.
     */
    public static function compute(int $timestamp, string $body, #[\SensitiveParameter] string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * O valor do cabeçalho de assinatura, com um `v1=` por segredo vigente.
     *
     * @param  list<string>  $secrets
     */
    public static function header(int $timestamp, string $body, #[\SensitiveParameter] array $secrets): string
    {
        $parts = ['t='.$timestamp];

        foreach ($secrets as $secret) {
            $parts[] = self::VERSION.'='.self::compute($timestamp, $body, $secret);
        }

        return implode(',', $parts);
    }
}
