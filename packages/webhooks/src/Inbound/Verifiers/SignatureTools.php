<?php

declare(strict_types=1);

namespace Twstec\Kit\Webhooks\Inbound\Verifiers;

use Twstec\Kit\Webhooks\Inbound\InvalidSourceConfiguration;
use Twstec\Kit\Webhooks\Inbound\Rejection;

/**
 * As peças comuns dos verificadores do pacote: lista de segredos (rotação),
 * janela de tempo, conteúdo assinado e leitura dos formatos de cabeçalho.
 *
 * @internal
 */
final class SignatureTools
{
    /**
     * Quantas assinaturas de um cabeçalho são conferidas, no máximo (um
     * cabeçalho com milhares de entradas não compra milhares de HMACs).
     */
    public const MAX_CANDIDATES = 16;

    /**
     * Segredos/chaves vigentes, sem vazios: string única ou lista (o atual e
     * o anterior, durante uma rotação). Null e texto vazio somem — segredo
     * vazio nunca confere nada.
     *
     * @return list<string>
     */
    public static function secrets(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $secrets = [];

        foreach ($values as $secret) {
            if (is_string($secret) && trim($secret) !== '') {
                $secrets[] = trim($secret);
            }
        }

        return array_values(array_unique($secrets));
    }

    /**
     * A janela de tempo: null quando o carimbo está dentro dela.
     */
    public static function window(int $timestamp, int $now, int $tolerance): ?Rejection
    {
        $tolerance = max(1, $tolerance);

        return match (true) {
            $timestamp < $now - $tolerance => Rejection::StaleTimestamp,
            $timestamp > $now + $tolerance => Rejection::FutureTimestamp,
            default => null,
        };
    }

    /**
     * Um carimbo de tempo em texto (só dígitos), já em segundos.
     */
    public static function timestamp(?string $value, string $unit = 'seconds'): ?int
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '' || strlen($value) > 16 || ! ctype_digit($value)) {
            return null;
        }

        $timestamp = (int) $value;

        return $unit === 'milliseconds' ? intdiv($timestamp, 1000) : $timestamp;
    }

    /**
     * O conteúdo assinado a partir do modelo (`{body}`, `{timestamp}.{body}`,
     * `{id}.{timestamp}.{body}`…). Null quando o modelo pede uma parte que não
     * veio. O `strtr` troca só os marcadores do MODELO: o corpo entra como
     * valor e nunca é relido (um `{id}` dentro do corpo fica como está).
     */
    public static function signedContent(string $template, string $body, ?string $id, ?string $timestamp): ?string
    {
        if (str_contains($template, '{id}') && ($id === null || $id === '')) {
            return null;
        }

        if (str_contains($template, '{timestamp}') && ($timestamp === null || $timestamp === '')) {
            return null;
        }

        return strtr($template, [
            '{body}' => $body,
            '{id}' => (string) $id,
            '{timestamp}' => (string) $timestamp,
        ]);
    }

    public static function assertTemplate(string $template): void
    {
        if (! str_contains($template, '{body}')) {
            throw new InvalidSourceConfiguration('O conteúdo assinado (signed_content) precisa conter {body}: a assinatura tem de cobrir o corpo.');
        }
    }

    public static function assertEncoding(string $encoding): void
    {
        if (! in_array($encoding, ['hex', 'base64'], true)) {
            throw new InvalidSourceConfiguration("Codificação de assinatura desconhecida: {$encoding} (use hex ou base64).");
        }
    }

    /**
     * As assinaturas candidatas de um cabeçalho, conforme o formato:
     *
     * - `plain`: o valor inteiro, depois do prefixo (`sha256=…`);
     * - `keyed`: pares `chave=valor` separados por vírgula (`t=…,v1=…,v1=…`) —
     *   todas as de `$signatureKey`; o carimbo em `$timestampKey`;
     * - `versioned`: entradas `versão,assinatura` separadas por espaço
     *   (`v1,… v1,…`) — todas as da versão `$signatureKey`.
     *
     * @return array{candidates: list<string>, timestamp: string|null}|null null = formato ilegível
     */
    public static function parseHeader(string $value, string $format, string $prefix = '', string $signatureKey = 'v1', ?string $timestampKey = null): ?array
    {
        $value = trim($value);

        if ($format === 'plain') {
            if ($prefix !== '') {
                if (! str_starts_with($value, $prefix)) {
                    return null;
                }

                $value = substr($value, strlen($prefix));
            }

            $value = trim($value);

            return $value === '' ? null : ['candidates' => [$value], 'timestamp' => null];
        }

        $candidates = [];
        $timestamp = null;
        $entries = $format === 'keyed'
            ? explode(',', $value)
            : (preg_split('/\s+/', $value) ?: []);

        foreach ($entries as $entry) {
            $separator = $format === 'keyed' ? '=' : ',';
            $parts = explode($separator, trim($entry), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$key, $content] = [trim($parts[0]), trim($parts[1])];

            if ($timestampKey !== null && $key === $timestampKey) {
                $timestamp = $content;
            } elseif ($key === $signatureKey && $content !== '' && count($candidates) < self::MAX_CANDIDATES) {
                $candidates[] = $content;
            }
        }

        return $candidates === [] ? null : ['candidates' => $candidates, 'timestamp' => $timestamp];
    }

    /**
     * Confere as candidatas contra as esperadas, em tempo constante, sem
     * parar na primeira (o tempo não diz qual segredo conferiu).
     *
     * @param  list<string>  $expected
     * @param  list<string>  $candidates
     */
    public static function matchesAny(array $expected, array $candidates, string $encoding): bool
    {
        $matched = false;

        foreach ($expected as $known) {
            foreach ($candidates as $candidate) {
                $candidate = $encoding === 'hex' ? strtolower($candidate) : $candidate;
                $matched = hash_equals($known, $candidate) || $matched;
            }
        }

        return $matched;
    }

    public static function encode(string $binary, string $encoding): string
    {
        return $encoding === 'hex' ? bin2hex($binary) : base64_encode($binary);
    }

    /**
     * A assinatura recebida de volta em bytes (verificação por chave
     * pública), ou null quando não decodifica.
     */
    public static function decode(string $signature, string $encoding): ?string
    {
        if ($encoding === 'hex') {
            return (strlen($signature) % 2 === 0 && ctype_xdigit($signature)) ? (string) hex2bin($signature) : null;
        }

        $decoded = base64_decode(strtr($signature, '-_', '+/'), true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * Opção de texto da configuração (ou o padrão).
     *
     * @param  array<string, mixed>  $options
     */
    public static function string(array $options, string $key, string $default = ''): string
    {
        $value = $options[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function nullableString(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * A tolerância da janela (segundos): a da fonte ou a padrão do pacote.
     *
     * @param  array<string, mixed>  $options
     */
    public static function tolerance(array $options): int
    {
        $value = $options['tolerance'] ?? config('webhooks.inbound.tolerance_seconds', 300);

        return max(1, is_numeric($value) ? (int) $value : 300);
    }
}
