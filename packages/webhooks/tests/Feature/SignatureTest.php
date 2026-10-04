<?php

declare(strict_types=1);

use Twstec\Kit\Webhooks\Signing\Signature;

// =============================================================================
// A ASSINATURA e o EXEMPLO DE VERIFICAÇÃO da documentação (o mesmo arquivo
// que docs/webhooks.md mostra e que o receptor de teste usa).
// =============================================================================

it('assina HMAC-SHA256 de "timestamp.corpo" — o exemplo da doc confere', function (): void {
    $secret = 'whsk_'.str_repeat('A', 43);
    $body = '{"id":"evt","type":"order.created","data":{"total":10}}';
    $now = 1_767_225_600;

    $header = Signature::header($now, $body, [$secret]);

    expect($header)->toBe('t='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, $secret))
        ->and(verify_webhook_signature($body, $header, $secret, 300, $now))->toBeTrue();
});

it('o exemplo da doc RECUSA carimbo velho (replay) e do futuro além da tolerância', function (): void {
    $secret = 'whsk_'.str_repeat('B', 43);
    $body = '{"id":"evt"}';
    $signed = 1_767_225_600;
    $header = Signature::header($signed, $body, [$secret]);

    expect(verify_webhook_signature($body, $header, $secret, 300, $signed + 300))->toBeTrue()
        ->and(verify_webhook_signature($body, $header, $secret, 300, $signed + 301))->toBeFalse()
        ->and(verify_webhook_signature($body, $header, $secret, 300, $signed - 301))->toBeFalse();
});

it('o exemplo da doc recusa corpo alterado, segredo errado, cabeçalho sem v1 ou sem t', function (): void {
    $secret = 'whsk_'.str_repeat('C', 43);
    $body = '{"id":"evt","total":10}';
    $now = 1_767_225_600;
    $header = Signature::header($now, $body, [$secret]);

    expect(verify_webhook_signature('{"id":"evt","total":99}', $header, $secret, 300, $now))->toBeFalse()
        ->and(verify_webhook_signature($body, $header, 'whsk_'.str_repeat('D', 43), 300, $now))->toBeFalse()
        ->and(verify_webhook_signature($body, 't='.$now, $secret, 300, $now))->toBeFalse()
        ->and(verify_webhook_signature($body, 'v1='.hash_hmac('sha256', $now.'.'.$body, $secret), $secret, 300, $now))->toBeFalse()
        // O carimbo faz parte do que é assinado: trocar o t= invalida.
        ->and(verify_webhook_signature($body, str_replace('t='.$now, 't='.($now + 1), $header), $secret, 300, $now))->toBeFalse();
});

it('na convivência, um v1 por segredo — o receptor com qualquer um dos dois confere', function (): void {
    $old = 'whsk_'.str_repeat('E', 43);
    $new = 'whsk_'.str_repeat('F', 43);
    $body = '{}';
    $now = 1_767_225_600;
    $header = Signature::header($now, $body, [$new, $old]);

    expect(substr_count($header, 'v1='))->toBe(2)
        ->and(verify_webhook_signature($body, $header, $old, 300, $now))->toBeTrue()
        ->and(verify_webhook_signature($body, $header, $new, 300, $now))->toBeTrue();
});

it('o exemplo compara em tempo constante (hash_equals) e lê o corpo bruto', function (): void {
    $code = (string) file_get_contents(dirname(__DIR__, 2).'/examples/verify-signature.php');

    expect($code)->toContain('hash_equals(')
        ->and($code)->not->toContain('==  $')
        ->and($code)->not->toMatch('/\$expected\s*===?\s*\$signature/');
});
