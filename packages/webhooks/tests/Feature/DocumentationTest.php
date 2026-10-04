<?php

declare(strict_types=1);

// =============================================================================
// O EXEMPLO DE VERIFICAÇÃO da documentação é o mesmo código que a suíte usa:
// docs/webhooks.md mostra a função do examples/verify-signature.php (PHP) e
// a do receptor do E2E dos starters (Node), sem divergir. Só no monorepo (os
// testes não vão no pacote publicado).
// =============================================================================

$monorepo = dirname(__DIR__, 4);

/**
 * Os blocos de código de uma linguagem num Markdown.
 *
 * @return list<string>
 */
function webhooksDocBlocks(string $markdown, string $language): array
{
    preg_match_all('/```'.$language.'\n(.*?)```/s', $markdown, $matches);

    return array_map('trim', $matches[1]);
}

it('a doc mostra a função PHP de verificação idêntica ao exemplo testado', function () use ($monorepo): void {
    $doc = (string) file_get_contents($monorepo.'/docs/webhooks.md');
    $example = (string) file_get_contents(dirname(__DIR__, 2).'/examples/verify-signature.php');
    $function = trim(substr($example, (int) strpos($example, 'function verify_webhook_signature')));

    expect(webhooksDocBlocks($doc, 'php'))->toContain($function);
})->skip(! is_file($monorepo.'/docs/webhooks.md'), 'fora do monorepo');

it('a doc mostra a função Node de verificação idêntica à do receptor do E2E dos dois starters', function () use ($monorepo): void {
    $doc = (string) file_get_contents($monorepo.'/docs/webhooks.md');
    $block = collect(webhooksDocBlocks($doc, 'js'))->first(fn (string $b): bool => str_contains($b, 'verifyWebhookSignature'));
    $function = trim(substr((string) $block, (int) strpos((string) $block, 'export function verifyWebhookSignature')));

    $livewire = (string) file_get_contents($monorepo.'/starters/livewire/tests/e2e/support/webhook-receiver.js');
    $react = (string) file_get_contents($monorepo.'/starters/react/tests/e2e/support/webhook-receiver.ts');

    // O do React é o mesmo, com os tipos do TypeScript.
    $semTipos = static fn (string $code): string => (string) preg_replace(['/const signatures: string\[\] = \[\];/', '/: string\b/', '/: boolean\b/'], ['const signatures = [];', '', ''], $code);

    expect($function)->not->toBe('')
        ->and($livewire)->toContain($function)
        ->and($semTipos($react))->toContain($function);
})->skip(! is_file($monorepo.'/docs/webhooks.md'), 'fora do monorepo');
