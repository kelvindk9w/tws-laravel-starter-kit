<?php

declare(strict_types=1);

namespace App\Core\Logging;

/**
 * Redaction de dados sensíveis antes de persistir logs (ADR-004 — LGPD).
 *
 * Regras:
 * - Chaves sensíveis (senhas, tokens, segredos, chaves de API, dados de
 *   cartão) são substituídas por '[REDACTED]' — casamento exato do nome
 *   da chave (case-insensitive) ou sufixo _token/_secret/_password/_api_key.
 * - CPF/CNPJ são mascarados parcialmente em QUALQUER valor string
 *   (mantém os 3 primeiros e 2 últimos dígitos).
 * - E-mails são mascarados parcialmente (primeira letra + domínio).
 * - Strings são truncadas (logs não são storage de payload gigante).
 *
 * A redaction é recursiva e cobre também as CHAVES do array (uma chave
 * maliciosa não vaza dado sensível como nome de campo).
 */
final class Redactor
{
    /**
     * Valor persistido no lugar de qualquer segredo.
     */
    public const MASK = '[REDACTED]';

    /**
     * Tamanho máximo de cada string persistida no log.
     */
    private const MAX_STRING_LENGTH = 1000;

    /**
     * Chaves (minúsculas) cujo valor é sempre mascarado.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'transaction_password',
        'senha', 'senha_confirmacao', 'senha_atual', 'senha_transacao',
        'token', 'access_token', 'refresh_token', 'id_token', 'api_key', 'api_secret',
        'secret', 'client_secret', 'authorization', 'private_key', 'webhook_secret',
        'card_number', 'card_cvv', 'cvv', 'cvc', 'card_expiry',
    ];

    /**
     * Sufixos de chave que denotam segredo (ex.: webhook_token, hmac_secret).
     *
     * @var list<string>
     */
    private const SENSITIVE_SUFFIXES = ['_token', '_secret', '_password', '_api_key'];

    /**
     * Redige recursivamente um array de dados (payload de requisição).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function redactArray(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::MASK;

                continue;
            }

            $redacted[$key] = match (true) {
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->truncate($this->redactString($value)),
                is_scalar($value) || $value === null => $value,
                default => '['.get_debug_type($value).']',
            };
        }

        return $redacted;
    }

    /**
     * Mascara CPF, CNPJ e e-mails dentro de uma string livre.
     *
     * Mantém os 3 primeiros e os 2 últimos dígitos do documento; o resto
     * vira '*', preservando a pontuação original. Exemplos:
     * CPF:  123.456.789-09    → 123.xxx.xxx-09 (x = dígito mascarado)
     * CNPJ: 12.345.678/0001-90 → 12.3xx.xxx/xxxx-90
     * E-mail: kelvin@exemplo.com → kXXX@exemplo.com (X = caracteres mascarados)
     */
    public function redactString(string $value): string
    {
        // CNPJ antes do CPF (14 dígitos conteriam um CPF no meio).
        $value = (string) preg_replace_callback(
            '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/',
            fn (array $m): string => $this->maskDigits($m[0]),
            $value,
        );

        $value = (string) preg_replace_callback(
            '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
            fn (array $m): string => $this->maskDigits($m[0]),
            $value,
        );

        return (string) preg_replace(
            '/\b([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*(@[A-Za-z0-9.-]+\.[A-Za-z]{2,})\b/',
            '$1***$2',
            $value,
        );
    }

    /**
     * A chave (nome do campo) denota um segredo?
     */
    public function isSensitiveKey(string $key): bool
    {
        $key = mb_strtolower($key);

        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mantém os 3 primeiros e 2 últimos dígitos; mascara o resto,
     * preservando a pontuação original (legibilidade para auditoria).
     */
    private function maskDigits(string $document): string
    {
        $digitIndex = 0;
        $total = strlen((string) preg_replace('/\D/', '', $document));

        return (string) preg_replace_callback(
            '/\d/',
            function (array $m) use (&$digitIndex, $total): string {
                $position = $digitIndex;
                $digitIndex++;

                return ($position < 3 || $position >= $total - 2) ? $m[0] : '*';
            },
            $document,
        );
    }

    /**
     * Trunca strings longas — logs guardam evidência, não o payload inteiro.
     */
    private function truncate(string $value): string
    {
        return mb_strlen($value) > self::MAX_STRING_LENGTH
            ? mb_substr($value, 0, self::MAX_STRING_LENGTH).'…[truncado]'
            : $value;
    }
}
