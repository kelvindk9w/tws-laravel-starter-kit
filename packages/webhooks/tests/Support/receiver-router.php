<?php

declare(strict_types=1);

// =============================================================================
// RECEPTOR DE TESTE (servidor embutido do PHP, só em 127.0.0.1) — faz o papel
// de quem recebe o webhook. Confere a assinatura com o EXEMPLO DA
// DOCUMENTAÇÃO (examples/verify-signature.php, o mesmo código de
// docs/webhooks.md) e registra cada requisição numa linha JSON do arquivo
// RECEIVER_LOG. O segredo esperado vem do arquivo RECEIVER_SECRETS (um por
// linha, o teste escreve). Nada sai da máquina.
// =============================================================================

require dirname(__DIR__, 2).'/examples/verify-signature.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$body = (string) file_get_contents('php://input');
$header = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
$secrets = array_values(array_filter(array_map('trim', explode("\n", (string) @file_get_contents((string) getenv('RECEIVER_SECRETS'))))));

$verifiedWith = [];

foreach ($secrets as $index => $secret) {
    if (verify_webhook_signature($body, $header, $secret)) {
        $verifiedWith[] = $index;
    }
}

$headers = [];

foreach ($_SERVER as $name => $value) {
    if (str_starts_with($name, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
    }
}

file_put_contents((string) getenv('RECEIVER_LOG'), json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'path' => $path,
    'headers' => $headers,
    'body' => $body,
    'verified_with' => $verifiedWith,
]).PHP_EOL, FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');

switch ($path) {
    case '/fail':
        http_response_code(500);
        echo '{"error":"falha simulada"}';
        break;
    case '/redirect':
        http_response_code(302);
        header('Location: http://10.0.0.1/collect');
        echo '';
        break;
    case '/echo':
        echo json_encode(['echo' => $secrets[0] ?? '', 'password' => 'p4ss-do-receptor', 'note' => 'contato: maria@example.com', 'padding' => str_repeat('x', 5000)]);
        break;
    case '/text':
        header('Content-Type: text/plain');
        echo 'token=abc123def segredo '.($secrets[0] ?? '').' fim';
        break;
    case '/slow':
        sleep(3);
        echo '{"ok":true}';
        break;
    default:
        echo '{"received":true}';
}
