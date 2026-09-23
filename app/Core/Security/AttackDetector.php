<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Detector de padrões maliciosos em inputs de requisição (ADR-005).
 *
 * Cobre as classes de ataque do checklist de segurança:
 * - XSS: <script, javascript:, handlers on*=, tags de embed perigosas
 * - SQLi: UNION SELECT, OR/AND tautológico com aspas, DROP/TRUNCATE TABLE,
 *   comandos empilhados após ponto-e-vírgula
 * - Null bytes (\0, inclusive URL-encoded %00)
 * - Path traversal (../, ..\\, inclusive URL-encoded %2e%2e)
 *
 * Os valores são URL-decodificados antes da análise para pegar ataques
 * ofuscados. O detector é conservador: prefere padrões com combinações
 * fortes (ex.: aspas + OR + comparação) para não bloquear texto legítimo.
 */
final class AttackDetector
{
    /**
     * Padrões por tipo de ataque. O tipo é persistido no request log
     * (metadado da tentativa — ADR-005).
     *
     * @var array<string, list<string>>
     */
    private const PATTERNS = [
        'xss' => [
            '/<\s*\/?\s*script\b/i',
            '/javascript\s*:/i',
            '/\bon\w+\s*=/i',
            '/<\s*(iframe|object|embed)\b/i',
        ],
        'sqli' => [
            '/\bunion\b\s+(all\s+)?select\b/i',
            '/(\'|")\s*(or|and)\s+[\w\'"]+\s*=\s*[\w\'"]+/i',
            '/\b(drop|truncate)\s+table\b/i',
            '/;\s*(drop|alter|truncate|insert|update|delete)\b/i',
            '/\b(select\b.+\bfrom\b|insert\s+into\b|delete\s+from\b)/i',
        ],
        'path_traversal' => [
            '/\.\.[\/\\\\]/',
        ],
    ];

    /**
     * Analisa recursivamente os dados (chaves e valores string) e retorna
     * o TIPO do primeiro ataque detectado ('xss', 'sqli', 'null_byte',
     * 'path_traversal') ou null quando está limpo.
     *
     * @param  array<array-key, mixed>|string  $input
     */
    public function detect(array|string $input): ?string
    {
        if (is_string($input)) {
            return $this->detectInString($input);
        }

        foreach ($input as $key => $value) {
            if (is_string($key) && ($type = $this->detectInString($key)) !== null) {
                return $type;
            }

            if (is_string($value) || is_array($value)) {
                if (($type = $this->detect($value)) !== null) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Os dados passam do teto de bytes inspecionáveis? Soma chaves e valores
     * de texto (o que `detect()` varreria) e para de contar assim que passa
     * do teto — a verificação custa, no máximo, o próprio teto, nunca o corpo
     * inteiro. Números e booleanos não contam (não passam por regex).
     *
     * Quem chama decide o que fazer com o excedente; o SecurityValidation
     * RECUSA (inspecionar só o começo deixaria o ataque escondido no fim).
     *
     * @param  array<array-key, mixed>  $input
     */
    public function exceedsInspectionBudget(array $input, int $maxBytes): bool
    {
        $remaining = $maxBytes;

        return $this->consume($input, $remaining);
    }

    /**
     * @param  array<array-key, mixed>  $input
     */
    private function consume(array $input, int &$remaining): bool
    {
        foreach ($input as $key => $value) {
            if (is_string($key)) {
                $remaining -= strlen($key);
            }

            if (is_string($value)) {
                $remaining -= strlen($value);
            } elseif (is_array($value) && $this->consume($value, $remaining)) {
                return true;
            }

            if ($remaining < 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analisa uma string individual (após URL-decode, para ofuscação).
     */
    public function detectInString(string $value): ?string
    {
        $decoded = rawurldecode($value);

        if (str_contains($value, "\0") || str_contains($decoded, "\0")) {
            return 'null_byte';
        }

        foreach (self::PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value) === 1 || preg_match($pattern, $decoded) === 1) {
                    return $type;
                }
            }
        }

        return null;
    }
}
