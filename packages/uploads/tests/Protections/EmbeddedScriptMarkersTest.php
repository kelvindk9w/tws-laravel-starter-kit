<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Twstec\Kit\Uploads\Exceptions\UploadRejectedException;
use Twstec\Kit\Uploads\Services\FileSecurityValidator;
use Twstec\Kit\Uploads\Services\SecureUploadService;

// =============================================================================
// MARCADORES DE SCRIPT EMBUTIDO SEM FALSO POSITIVO.
//
// Os marcadores LONGOS (<?php, <script) valem no arquivo inteiro, em todo
// tipo. Os CURTOS (<?=, #!/), de 3 bytes, aparecem por acaso em dados
// comprimidos (JPEG, PNG, PDF, MP4...): no arquivo inteiro só nos tipos de
// TEXTO; nos binários, só nos primeiros 1024 bytes. Antes, uma foto real de
// ~2 MB tinha perto de 1 chance em 4 de ser recusada por engano (medição
// com fotos reais: 9 de 20 recusadas).
// =============================================================================

/**
 * Valida os bytes direto no núcleo e devolve o motivo da recusa (ou null,
 * se aceito).
 *
 * @param  list<string>  $tipos
 */
function motivoDaRecusa(string $bytes, string $extensao, array $tipos): ?string
{
    try {
        app(FileSecurityValidator::class)->validate($bytes, $extensao, $tipos);
    } catch (UploadRejectedException $exception) {
        return $exception->reason;
    }

    return null;
}

/**
 * Tipos de TEXTO, fora do catálogo padrão — como uma aplicação declararia.
 */
function comTiposDeTexto(): void
{
    config([
        'uploads.types.texto' => ['mimes' => ['text/plain' => 'txt', 'text/csv' => 'csv'], 'max_kb' => 1024],
        'uploads.types.vetor' => ['mimes' => ['image/svg+xml' => 'svg'], 'max_kb' => 1024],
    ]);
}

// --- Regressão: o que era recusado por engano e agora entra -----------------

it('20 JPEGs de ~2 MB com ruído, sem marcador posto de propósito: 20 de 20 aceitos', function (): void {
    $aceitos = 0;
    $comMarcadorCurtoPorAcaso = 0;

    foreach (range(1, 20) as $semente) {
        $jpeg = fixtureBytesJpegComRuido(1600, 1200, $semente);

        expect(strlen($jpeg))->toBeGreaterThan(1_500_000);

        $minusculo = strtolower($jpeg);

        if (str_contains($minusculo, '<?=') || str_contains($minusculo, '#!/')) {
            $comMarcadorCurtoPorAcaso++;
        }

        if (motivoDaRecusa($jpeg, 'jpg', ['image']) === null) {
            $aceitos++;
        }
    }

    // O teste exercita o defeito: parte das fotos traz <?= ou #!/ por acaso
    // (a versão anterior recusava exatamente essas).
    expect($comMarcadorCurtoPorAcaso)->toBeGreaterThan(0)
        ->and($aceitos)->toBe(20);
});

it('JPEG com <?= num comentário depois dos primeiros 1024 bytes: aceito, e o gravado não tem mais o <?=', function (): void {
    $jpeg = fixtureJpegComComentario(fixtureBytesJpegComRuido(64, 64, 7), 'x <?= y #!/z', 1100);

    expect(strpos($jpeg, '<?='))->toBeGreaterThan(1024);

    $upload = $this->inAccountOf(null, fn () => app(SecureUploadService::class)->handle(fixtureArquivoEnviado($jpeg, 'foto.jpg')));
    $gravado = (string) Storage::disk($upload->disk)->get($upload->path);

    expect($upload->mime)->toBe('image/jpeg')
        ->and($gravado)->not->toBe('')
        ->and($gravado)->not->toContain('<?=')
        ->and($gravado)->not->toContain('#!/');
});

it('PDF com stream binário que traz <?= e #!/ no meio: aceito', function (): void {
    $pdf = fixtureBytesPdfComStreamBinario("\x00<?=\x01#!/\x02");

    expect(strpos($pdf, '<?='))->toBeGreaterThan(1024)
        ->and(motivoDaRecusa($pdf, 'pdf', ['pdf']))->toBeNull();
});

// --- Segurança: o que era recusado continua recusado -------------------------

it('PHP disfarçado de imagem: recusado pelo tipo real ou pelo marcador longo', function (): void {
    // GIF89a seguido de PHP: GIF não está na lista (tipo real).
    expect(motivoDaRecusa('GIF89a<?php system($_GET[1]); ?>', 'jpg', ['image']))->toBe('mime_not_allowed')
        // JPEG válido com <?php no meio (bem depois do cabeçalho).
        ->and(motivoDaRecusa(fixtureJpegComComentario(fixtureBytesJpegComRuido(64, 64, 3), '<?php system($_GET[1]); ?>', 4096), 'jpg', ['image']))->toBe('embedded_script')
        // Caixa alternada também (a procura é sem diferenciar maiúsculas).
        ->and(motivoDaRecusa(fixtureBytesPng().str_repeat("\0", 4096).'<?PhP echo 1;', 'png', ['image']))->toBe('embedded_script')
        // <script em qualquer posição de um binário.
        ->and(motivoDaRecusa(fixtureBytesPng().str_repeat("\0", 4096).'<SCRIPT>alert(1)</script>', 'png', ['image']))->toBe('embedded_script')
        // PDF com <?php no meio de um stream.
        ->and(motivoDaRecusa(fixtureBytesPdfComStreamBinario('<?php echo 1; ?>'), 'pdf', ['pdf']))->toBe('embedded_script');
});

it('SVG com <script>: recusado pelo tipo; e, se um dia for aceito, o marcador pega', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

    expect(motivoDaRecusa($svg, 'svg', ['image']))->toBe('mime_not_allowed');

    comTiposDeTexto();

    $profundo = '<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat('<g/>', 1000).'<?= 1 ?></svg>';

    expect(motivoDaRecusa($svg, 'svg', ['vetor']))->toBe('embedded_script')
        ->and(motivoDaRecusa($profundo, 'svg', ['vetor']))->toBe('embedded_script')
        ->and(motivoDaRecusa('<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>', 'svg', ['vetor']))->toBeNull();
});

it('PDF com /JavaScript ou /OpenAction: recusado (pdf_auto_action)', function (): void {
    expect(motivoDaRecusa(fixtureBytesPdfComJavaScript(), 'pdf', ['pdf']))->toBe('pdf_auto_action')
        ->and(motivoDaRecusa(str_replace('/JavaScript/JS (app.alert(\'x\'))', '/URI', fixtureBytesPdfComJavaScript()), 'pdf', ['pdf']))->toBe('pdf_auto_action');
});

it('arquivo de TEXTO com <?= ou #!/ em qualquer posição: recusado', function (): void {
    comTiposDeTexto();

    $texto = str_repeat("Linha de texto comum.\n", 200);

    expect(strlen($texto))->toBeGreaterThan(1024)
        ->and(motivoDaRecusa($texto, 'txt', ['texto']))->toBeNull()
        ->and(motivoDaRecusa($texto.'<?= $x ?>', 'txt', ['texto']))->toBe('embedded_script')
        ->and(motivoDaRecusa($texto.'executa #!/bin/sh', 'txt', ['texto']))->toBe('embedded_script')
        ->and(motivoDaRecusa("nome,valor\n".str_repeat("a,1\n", 400)."b,<?= 1 ?>\n", 'csv', ['texto']))->toBe('embedded_script');
});

it('binário com <?= ou #!/ nos primeiros 1024 bytes: recusado', function (): void {
    $jpeg = fixtureBytesJpegComRuido(64, 64, 5);

    expect(motivoDaRecusa(fixtureJpegComComentario($jpeg, '#!/bin/sh'), 'jpg', ['image']))->toBe('embedded_script')
        ->and(motivoDaRecusa(fixtureJpegComComentario($jpeg, '<?= 1 ?>', 900), 'jpg', ['image']))->toBe('embedded_script')
        ->and(motivoDaRecusa(fixtureBytesPdfComStreamBinario('<?=', 200), 'pdf', ['pdf']))->toBe('embedded_script')
        // O mesmo marcador depois da janela: aceito (é o caso do falso positivo).
        ->and(motivoDaRecusa(fixtureJpegComComentario($jpeg, '#!/bin/sh', 1100), 'jpg', ['image']))->toBeNull();
});

it('executável (MZ, ELF): recusado (executable), como antes', function (): void {
    expect(motivoDaRecusa('MZ'.str_repeat("\x90", 512), 'pdf', ['pdf']))->toBe('executable')
        ->and(motivoDaRecusa(fixtureBytesElf(), 'jpg', ['image']))->toBe('executable')
        ->and(motivoDaRecusa("#!/bin/sh\necho 1\n", 'txt', ['image', 'pdf']))->toBe('executable');
});
