<?php

declare(strict_types=1);

// =============================================================================
// O DOCKER DE PRODUÇÃO (docker-compose.prod.yml, docker/nginx, o entrypoint
// da imagem do app). Travas baratas, lidas nos arquivos; a prova de ponta a
// ponta é a simulação da instalação publicada no CI (as imagens construídas).
//
// - o public/ é SINCRONIZADO no volume do nginx (o que sobrou da versão
//   anterior sai), e falha ali para a subida.
// =============================================================================

function productionFile(string $path): string
{
    return (string) file_get_contents(base_path($path));
}

it('o public/ é sincronizado no volume do nginx: o que sobrou da versão anterior sai, e a falha para a subida', function (): void {
    $entrypoint = productionFile('docker/php/entrypoint-prod.sh');

    preg_match('/^if \[ -d \/app-public \]; then\n(.*?)^fi$/ms', $entrypoint, $sync);

    expect($sync[1] ?? '')->toContain('cp -a /var/www/html/public/. /app-public/')
        ->toContain('find . -mindepth 1 -depth')
        ->toContain('rm -rf "./$entry"')
        ->not->toContain('|| true')
        ->not->toContain('2>/dev/null')
        ->and($entrypoint)->toMatch('/^set -e$/m');
});
