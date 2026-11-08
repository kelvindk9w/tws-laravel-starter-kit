<?php

declare(strict_types=1);

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Twstec\Kit\Webhooks\Inbound\InboundRequest;
use Twstec\Kit\Webhooks\Tests\Fixtures\RecordingInboundHandler;

// =============================================================================
// Apoio dos testes dos webhooks RECEBIDOS: fontes de exemplo, assinaturas
// feitas aqui (do jeito que o provedor faria) e a captura do log.
// Os segredos são de teste, gerados na hora.
// =============================================================================

const INBOUND_TOKEN = 'tok_teste_atual_0123456789abcdef';
const INBOUND_TOKEN_PREVIOUS = 'tok_teste_anterior_fedcba9876543210';
const INBOUND_HMAC_SECRET = 'hmac_teste_atual_0123456789abcdef';
const INBOUND_HMAC_SECRET_PREVIOUS = 'hmac_teste_anterior_fedcba987654';

/**
 * As fontes de exemplo, todas com o handler de teste:
 * - `token`: token fixo em cabeçalho (o caso de um provedor sem HMAC);
 * - `corpo`: HMAC-SHA256 hex do corpo, `sha256=` no cabeçalho;
 * - `carimbo`: HMAC com carimbo `t=…,v1=…` (vários v1 na rotação);
 * - `padrao`: Standard Webhooks.
 *
 * @param  array<string, array<string, mixed>>  $overrides  fonte => chaves que trocam
 */
function inboundSources(array $overrides = []): void
{
    $sources = [
        'token' => [
            'verifier' => ['driver' => 'header_token', 'header' => 'x-provedor-token', 'secrets' => [INBOUND_TOKEN, INBOUND_TOKEN_PREVIOUS]],
            'handler' => RecordingInboundHandler::class,
            'event' => ['id' => 'id', 'type' => 'event', 'occurred_at' => 'dateCreated', 'timezone' => 'America/Sao_Paulo'],
        ],
        'corpo' => [
            'verifier' => ['driver' => 'hmac', 'header' => 'X-Hub-Signature-256', 'prefix' => 'sha256=', 'secrets' => [INBOUND_HMAC_SECRET]],
            'handler' => RecordingInboundHandler::class,
            'event' => ['id' => 'entry.0.id', 'type' => 'object'],
        ],
        'carimbo' => [
            'verifier' => ['driver' => 'hmac', 'header' => 'Assinatura', 'format' => 'keyed', 'timestamp_key' => 't', 'signature_key' => 'v1', 'secrets' => [INBOUND_HMAC_SECRET, INBOUND_HMAC_SECRET_PREVIOUS]],
            'handler' => RecordingInboundHandler::class,
            'event' => ['id' => 'id', 'type' => 'type', 'occurred_at' => 'created'],
        ],
        'padrao' => [
            'verifier' => ['driver' => 'standard_webhooks', 'secrets' => [standardSecret('atual')]],
            'handler' => RecordingInboundHandler::class,
            'event' => ['type' => 'type'],
        ],
    ];

    foreach ($overrides as $name => $values) {
        $sources[$name] = [...($sources[$name] ?? []), ...$values];
    }

    config(['webhooks.inbound_sources' => $sources]);
}

/**
 * Um segredo Standard Webhooks (`whsec_<base64>`), estável por rótulo.
 */
function standardSecret(string $label): string
{
    return 'whsec_'.base64_encode(hash('sha256', 'segredo-standard-'.$label, true));
}

/**
 * @return array<string, string>
 */
function standardHeaders(string $body, string $id, int $timestamp, string ...$secrets): array
{
    $signatures = [];

    foreach ($secrets as $secret) {
        $key = (string) base64_decode(substr($secret, strlen('whsec_')), true);
        $signatures[] = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));
    }

    return ['webhook-id' => $id, 'webhook-timestamp' => (string) $timestamp, 'webhook-signature' => implode(' ', $signatures)];
}

function keyedSignature(string $body, int $timestamp, string ...$secrets): string
{
    $parts = ['t='.$timestamp];

    foreach ($secrets as $secret) {
        $parts[] = 'v1='.hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    return implode(',', $parts);
}

/**
 * Um corpo de evento com um MARCADOR único — o texto que nunca pode
 * aparecer em log, trilha ou fila.
 *
 * @param  array<string, mixed>  $extra
 */
function inboundBody(string $id = 'evt_0001', string $marker = 'MARCADOR-DO-CORPO-7f3a', array $extra = []): string
{
    return (string) json_encode([
        'id' => $id,
        'event' => 'PAYMENT_RECEIVED',
        'dateCreated' => '2026-11-07 10:15:00',
        'payment' => ['id' => 'pay_123', 'description' => $marker, 'customer' => 'Cliente '.$marker],
        ...$extra,
    ]);
}

/**
 * POST do corpo CRU (bytes exatos) na rota da fonte.
 *
 * @param  array<string, string>  $headers
 * @param  array<string, string>  $server
 */
function postInbound(string $source, string $body, array $headers = [], array $server = []): TestResponse
{
    $serverVars = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', ...$server];

    foreach ($headers as $name => $value) {
        $serverVars['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', '/webhooks/inbound/'.$source, [], [], [], $serverVars, $body);
}

/**
 * Captura tudo o que vai para o log (mensagem + contexto, serializados).
 *
 * @return ArrayObject<int, string>
 */
function captureLogs(): ArrayObject
{
    /** @var ArrayObject<int, string> $lines */
    $lines = new ArrayObject;

    Event::listen(MessageLogged::class, function (MessageLogged $event) use ($lines): void {
        $lines[] = $event->level.' '.$event->message.' '.json_encode($event->context);
    });

    return $lines;
}

/**
 * @param  array<string, string>  $headers
 */
function inboundRequest(string $body, array $headers = [], ?int $now = null): InboundRequest
{
    $normalized = [];

    foreach ($headers as $name => $value) {
        $normalized[strtolower($name)] = [$value];
    }

    return new InboundRequest($body, $normalized, $now ?? time());
}

/**
 * Um par de chaves RSA de teste (PEM), gerado na hora.
 *
 * @return array{private: OpenSSLAsymmetricKey, public: string}
 */
function rsaKeyPair(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key instanceof OpenSSLAsymmetricKey);
    $details = openssl_pkey_get_details($key);

    return ['private' => $key, 'public' => (string) ($details['key'] ?? '')];
}

function rsaSign(string $content, OpenSSLAsymmetricKey $private): string
{
    openssl_sign($content, $signature, $private, OPENSSL_ALGO_SHA256);

    return base64_encode((string) $signature);
}
