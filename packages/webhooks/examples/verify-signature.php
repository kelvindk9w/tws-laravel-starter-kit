<?php

declare(strict_types=1);

/**
 * Confere a assinatura de um webhook do TWS Laravel Starter Kit.
 *
 * - $payload: o CORPO BRUTO da requisição, exatamente como chegou
 *   (ex.: file_get_contents('php://input') ou $request->getContent()) —
 *   nunca o JSON decodificado e codificado de novo.
 * - $header: o valor do cabeçalho X-Webhook-Signature
 *   ("t=1767225600,v1=…[,v1=…]").
 * - $secret: o segredo do endpoint (whsk_…), como foi copiado da tela.
 * - $tolerance: diferença máxima, em segundos, entre o carimbo e o relógio do
 *   receptor (contra replay). Padrão: 5 minutos.
 *
 * Devolve true só quando o carimbo está dentro da tolerância E algum `v1`
 * confere (durante a troca de segredo chegam dois). A comparação é em tempo
 * constante (hash_equals). Depois de conferir, deduplique pelo `id` do
 * evento (o mesmo em toda tentativa e reenvio).
 */
function verify_webhook_signature(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
{
    $timestamp = null;
    $signatures = [];

    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

        if ($key === 't' && ctype_digit($value)) {
            $timestamp = (int) $value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || $signatures === []) {
        return false;
    }

    if (abs(($now ?? time()) - $timestamp) > $tolerance) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
