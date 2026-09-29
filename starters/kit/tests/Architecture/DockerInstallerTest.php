<?php

declare(strict_types=1);

use Twstec\Kit\Setup\Choice;

// =============================================================================
// O INSTALADOR EM CONTAINER (`docker compose run --rm instalar`): o
// compose.yaml e a imagem do twstec/kit. Travas do que quebraria em silêncio —
// uma variável de escolha nova que o compose não repassa, a imagem do teste de
// porta diferente da do instalador, o roteiro fora do ZIP.
// =============================================================================

$root = dirname(__DIR__, 2);

it('o compose.yaml repassa TODAS as variáveis de escolha (e o idioma) ao container', function () use ($root): void {
    $compose = (string) file_get_contents($root.'/compose.yaml');

    foreach ([...Choice::VARIABLES, 'TWS_KIT_LOCALE', 'TWS_KIT_UID', 'TWS_KIT_GID'] as $variable) {
        // Sem valor: repassa só se estiver no ambiente de quem chamou (com
        // valor vazio, qualquer uma desligaria o menu).
        expect($compose)->toMatch('/^\s+- '.$variable.'$/m');
    }

    expect($compose)->toContain('- TWS_KIT_IN_DOCKER=1')
        ->toContain('- TWS_KIT_FOLDER=${COMPOSE_PROJECT_NAME}');
});

it('a imagem do teste de porta é a do próprio instalador (a mesma versão)', function () use ($root): void {
    $compose = (string) file_get_contents($root.'/compose.yaml');

    preg_match('/^\s+image: (twstec-kit-instalar:\S+)$/m', $compose, $image);
    preg_match('/TWS_KIT_PROBE_IMAGE=(\S+)$/m', $compose, $probe);

    expect($image[1] ?? null)->not->toBeNull()
        ->and($probe[1] ?? null)->toBe($image[1]);
});

it('o roteiro é montado da pasta (a imagem de uma versão anterior roda o desta), sem CRLF e sem rede ou volume que fiquem para trás', function () use ($root): void {
    $compose = (string) file_get_contents($root.'/compose.yaml');
    $dockerfile = (string) file_get_contents($root.'/docker/instalar/Dockerfile');

    expect($compose)->toContain('- ./docker/instalar/entrypoint.sh:/opt/twstec-kit/entrypoint.sh:ro')
        ->toContain('network_mode: bridge')
        ->not->toMatch('/^volumes:/m')
        ->and($dockerfile)->not->toContain('COPY entrypoint.sh')
        ->toContain("sed 's/\\\\r$//' /opt/twstec-kit/entrypoint.sh")
        ->and((string) file_get_contents($root.'/docker/instalar/entrypoint.sh'))->not->toContain("\r");
});

it('o ZIP do GitHub e o do Composer levam o instalador em container (e só a suíte fica de fora), com LF sempre', function () use ($root): void {
    $attributes = (string) file_get_contents($root.'/.gitattributes');

    expect($attributes)->toContain('* text=auto eol=lf')
        ->not->toMatch('~^/(compose\.yaml|docker)\b.*export-ignore~m')
        ->toContain('/tests export-ignore');
});
