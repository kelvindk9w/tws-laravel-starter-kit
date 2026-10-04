<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Delivery;

use Psr\Http\Message\StreamInterface;
use Throwable;
use Twstec\Kit\Foundation\Logging\Redactor;

/**
 * O TRECHO da resposta do receptor que vai para o log de entregas.
 *
 * - CORTADO: só os primeiros `webhooks.delivery.response_excerpt_bytes` bytes
 *   são LIDOS (o resto nem sai da rede para a memória);
 * - REDIGIDO: os segredos do endpoint (se o receptor ecoar) viram
 *   `[REDACTED]`; JSON completo passa pelo Redactor campo a campo (senha,
 *   token, segredo, assinatura...); texto passa pelas máscaras do Redactor
 *   (CPF/CNPJ, e-mail, cartão) e pela máscara de `chave=valor` /
 *   `"chave":"valor"` dos nomes sensíveis;
 * - UTF-8 válido sempre (bytes inválidos são trocados).
 */
final class ResponseExcerpt
{
    private const SENSITIVE_PAIR = '/(["\']?)(password|passwd|senha|token|access_token|refresh_token|secret|client_secret|api_key|apikey|authorization|signature|webhook_secret)\1(\s*[:=]\s*)("[^"]*"|\'[^\']*\'|[^\s,;&}]+)/i';

    public function __construct(private readonly Redactor $redactor) {}

    public static function limit(): int
    {
        return max(0, min(65536, (int) config('webhooks.delivery.response_excerpt_bytes', 1024)));
    }

    /**
     * Lê no máximo o limite do stream.
     */
    public static function read(StreamInterface $stream): string
    {
        $limit = self::limit();
        $read = '';

        try {
            while (strlen($read) < $limit && ! $stream->eof()) {
                $chunk = $stream->read($limit - strlen($read));

                if ($chunk === '') {
                    break;
                }

                $read .= $chunk;
            }
        } catch (Throwable) {
            // O que deu para ler fica; a falha de leitura não muda o status.
        }

        return $read;
    }

    /**
     * @param  list<string>  $secrets
     */
    public function redact(string $raw, #[\SensitiveParameter] array $secrets): ?string
    {
        if ($raw === '') {
            return null;
        }

        $text = mb_scrub($raw, 'UTF-8');

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, Redactor::MASK, $text);
            }
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            $encoded = json_encode($this->redactor->redactArray($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($encoded)) {
                return $encoded;
            }
        }

        $text = (string) preg_replace(self::SENSITIVE_PAIR, '$1$2$1$3'.Redactor::MASK, $text);

        return $this->redactor->redactString($text);
    }
}
