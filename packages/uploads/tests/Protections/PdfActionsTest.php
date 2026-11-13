<?php

declare(strict_types=1);

use Twstec\Kit\Uploads\Exceptions\UploadRejectedException;
use Twstec\Kit\Uploads\Services\FileSecurityValidator;
use Twstec\Kit\Uploads\Services\SecureUploadService;

// =============================================================================
// PDF COM JAVASCRIPT OU AÇÃO AUTOMÁTICA, SEM FALSO POSITIVO.
//
// Os nomes perigosos (/JS, /JavaScript, /OpenAction, /AA, /Launch) e os de
// anexo (/EmbeddedFile, /EF...) são procurados onde o leitor de PDF lê OBJETOS: fora dos
// dados de stream, com #xx decodificado e dentro dos streams de objetos
// (/ObjStm), descomprimidos. Antes, o arquivo inteiro era varrido, e a foto
// comprimida de um PDF escaneado formava /JS ou /AA por acaso (~3% de chance
// num PDF de 5 MB, ~11% num de 16 MB).
// =============================================================================

/**
 * Valida o PDF no núcleo e devolve o motivo da recusa (ou null, se aceito).
 */
function pdfaMotivo(string $pdf): ?string
{
    try {
        app(FileSecurityValidator::class)->validate($pdf, 'pdf', ['pdf']);
    } catch (UploadRejectedException $exception) {
        return $exception->reason;
    }

    return null;
}

/**
 * Um PDF com os objetos dados (o 1 é o catálogo, que aponta as páginas no 2).
 *
 * @param  array<int, string>  $objetos  número => corpo do objeto
 */
function pdfaDe(array $objetos, string $catalogo = ''): string
{
    $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n"
        ."1 0 obj\n<</Type/Catalog/Pages 2 0 R{$catalogo}>>\nendobj\n"
        ."2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n"
        ."3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>\nendobj\n";

    foreach ($objetos as $numero => $corpo) {
        $pdf .= "{$numero} 0 obj\n{$corpo}\nendobj\n";
    }

    return $pdf."trailer\n<</Root 1 0 R>>\n%%EOF\n";
}

/**
 * Um stream com o /Length certo.
 */
function pdfaStream(string $dicionario, string $dados): string
{
    return "<<{$dicionario}/Length ".strlen($dados).">>\nstream\n{$dados}\nendstream";
}

/**
 * Um stream de objetos (/ObjStm) com os objetos dados, no formato da norma
 * (pares "número posição" e depois os objetos).
 *
 * @param  array<int, string>  $objetos
 */
function pdfaObjStm(array $objetos): array
{
    $cabecalho = '';
    $corpo = '';

    foreach ($objetos as $numero => $objeto) {
        $cabecalho .= $numero.' '.strlen($corpo).' ';
        $corpo .= $objeto."\n";
    }

    return ['first' => strlen($cabecalho), 'n' => count($objetos), 'dados' => $cabecalho.$corpo];
}

/**
 * Bytes binários de enchimento (0x80–0xFF: nunca formam '/', '<' nem '(').
 */
function pdfaBinario(int $tamanho): string
{
    $bytes = '';

    for ($i = 0; $i < $tamanho; $i++) {
        $bytes .= chr(0x80 + ($i * 37) % 0x80);
    }

    return $bytes;
}

// --- Regressão: o que era recusado por engano e agora entra -----------------

it('PDF escaneado de ~5 MB: a foto da página entra, e /JS ou /AA nos bytes dela não contam', function (): void {
    $foto = fixtureBytesJpegComRuido(2400, 1800, 5);
    $escaneado = pdfaDe([4 => pdfaStream('/Type/XObject/Subtype/Image/Width 2400/Height 1800/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/DCTDecode', $foto)]);

    expect(strlen($escaneado))->toBeGreaterThan(4_500_000)
        ->and(pdfaMotivo($escaneado))->toBeNull();

    // Os nomes perigosos DENTRO dos dados binários de um stream (como os que
    // uma foto forma por acaso): aceito. A varredura antiga, no arquivo
    // inteiro, recusava.
    $acaso = pdfaDe([4 => pdfaStream('/Type/XObject/Subtype/Image/Width 1/Height 1/ColorSpace/DeviceGray/BitsPerComponent 8', pdfaBinario(2048)."\x00/JS \x01/AA>\x02/OpenAction(\x03/J#53 \x04/Launch[".pdfaBinario(2048))]);

    expect(preg_match('/\/JS(?=[\s\(\)<>\[\]\/%]|$)/', $acaso))->toBe(1)
        ->and(pdfaMotivo($acaso))->toBeNull();
});

it('stream com /Length indireto (num objeto inteiro): os dados continuam fora da procura', function (): void {
    $dados = pdfaBinario(1000).'/JS '.pdfaBinario(1000);
    $pdf = pdfaDe([
        4 => "<</Type/XObject/Subtype/Image/Width 1/Height 1/ColorSpace/DeviceGray/BitsPerComponent 8/Length 5 0 R>>\nstream\n{$dados}\nendstream",
        5 => (string) strlen($dados),
    ]);

    expect(pdfaMotivo($pdf))->toBeNull();
});

it('stream de objetos (/ObjStm) sem nada perigoso: descomprimido, conferido e aceito', function (): void {
    $objstm = pdfaObjStm([10 => '<</Type/Annot/Subtype/Link/Rect[0 0 10 10]/Border[0 0 0]>>', 11 => '<</Producer(Scanner)>>']);

    expect(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N {$objstm['n']}/First {$objstm['first']}/Filter/FlateDecode", (string) gzcompress($objstm['dados']))])))->toBeNull();
});

// --- Segurança: o que tem de continuar recusado ------------------------------

it('/JS, /JavaScript, /OpenAction, /AA e /Launch num dicionário comum: recusados (pdf_auto_action)', function (): void {
    expect(pdfaMotivo(fixtureBytesPdfComJavaScript()))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</S/JavaScript/JS(app.alert(1))>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</JS 5 0 R>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([], '/OpenAction[3 0 R/Fit]')))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</Type/Page/AA<</O 5 0 R>>>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</S/Launch/F(cmd.exe)>>'])))->toBe('pdf_auto_action');
});

it('PDF com arquivo anexado: recusado com o motivo próprio, que diz o que fazer', function (): void {
    expect(pdfaMotivo(pdfaDe([4 => pdfaStream('/Type/EmbeddedFile', 'MZ arquivo anexado')])))->toBe('pdf_attachment_or_password')
        ->and(pdfaMotivo(pdfaDe([], '/Names<</EmbeddedFiles 4 0 R>>')))->toBe('pdf_attachment_or_password')
        ->and(pdfaMotivo(pdfaDe([4 => '<</Type/Filespec/F(nota.xml)/EF<</F 5 0 R>>>>', 5 => pdfaStream('', '<nota/>')])))->toBe('pdf_attachment_or_password')
        ->and(pdfaMotivo(pdfaDe([4 => '<</Type/Annot/Subtype/FileAttachment/FS 5 0 R>>'])))->toBe('pdf_attachment_or_password')
        ->and(pdfaMotivo(pdfaDe([4 => '<</Type/Embedded#46ile>>'])))->toBe('pdf_attachment_or_password')
        // Anexo e JavaScript juntos: vale o motivo mais grave.
        ->and(pdfaMotivo(pdfaDe([4 => '<</S/JavaScript/JS(app.alert(1))>>', 5 => '<</EF<</F 6 0 R>>>>'])))->toBe('pdf_auto_action');
});

it('PDF cifrado (com senha) com stream de objetos: recusado com o motivo próprio; cifrado sem stream de objetos continua entrando', function (): void {
    $objstm = pdfaObjStm([10 => '<</Producer(Scanner)>>']);
    $cifra = '<</Filter/Standard/V 2/R 3/Length 128/P -1068/O<'.str_repeat('ab', 32).'>/U<'.str_repeat('cd', 32).'>>>';
    $comObjStm = str_replace("trailer\n<</Root 1 0 R>>", "trailer\n<</Root 1 0 R/Encrypt 9 0 R>>", pdfaDe([
        4 => pdfaStream("/Type/ObjStm/N 1/First {$objstm['first']}/Filter/FlateDecode", pdfaBinario(300)),
        9 => $cifra,
    ]));
    $semObjStm = str_replace("trailer\n<</Root 1 0 R>>", "trailer\n<</Root 1 0 R/Encrypt 9 0 R>>", pdfaDe([9 => $cifra]));

    expect(pdfaMotivo($comObjStm))->toBe('pdf_attachment_or_password')
        ->and(pdfaMotivo($semObjStm))->toBeNull();
});

it('a recusa de anexo ou senha chega pela função única de upload com a mensagem traduzida do pacote', function (): void {
    $anexo = fixtureArquivoEnviado(pdfaDe([4 => pdfaStream('/Type/EmbeddedFile', 'MZ arquivo anexado')]), 'contrato.pdf');

    app()->setLocale('pt_BR');

    try {
        $this->inAccountOf(null, fn () => app(SecureUploadService::class)->handle($anexo));
        $this->fail('O PDF com anexo deveria ter sido recusado.');
    } catch (UploadRejectedException $recusa) {
        expect($recusa->reason)->toBe('pdf_attachment_or_password')
            ->and($recusa->getMessage())->toBe('Este PDF tem anexo ou senha. Salve sem anexo e sem senha e envie de novo.');
    }

    app()->setLocale('en');
    expect(__('uploads.rejected.pdf_attachment_or_password'))->toBe('This PDF has an attachment or a password. Save it without attachments and without a password and upload it again.');

    app()->setLocale('es');
    expect(__('uploads.rejected.pdf_attachment_or_password'))->toBe('Este PDF tiene un adjunto o una contraseña. Guárdelo sin adjuntos y sin contraseña y envíelo de nuevo.');
});

it('/JS dentro de um stream de objetos (/ObjStm): recusado, comprimido ou não, declarado ou não', function (): void {
    $objstm = pdfaObjStm([10 => '<</S/JavaScript/JS(app.alert(1))>>']);
    $flate = (string) gzcompress($objstm['dados']);

    expect(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$objstm['first']}/Filter/FlateDecode", $flate)])))->toBe('pdf_auto_action')
        // Filtro em lista e nome do tipo em hexadecimal.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/Obj#53tm/N 1/First {$objstm['first']}/Filter[/FlateDecode]", $flate)])))->toBe('pdf_auto_action')
        // Sem /Type: /First e /N bastam para leitores como o pdf.js.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/N 1/First {$objstm['first']}/Filter/FlateDecode", $flate)])))->toBe('pdf_auto_action')
        // Sem compressão.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$objstm['first']}", $objstm['dados'])])))->toBe('pdf_auto_action');
});

it('stream de objetos que não dá para ler: recusado (filtro desconhecido, preditor, dado corrompido, acima do teto)', function (): void {
    $limpo = pdfaObjStm([10 => '<</Producer(Scanner)>>']);
    $first = $limpo['first'];

    expect(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$first}/Filter/ASCIIHexDecode", bin2hex($limpo['dados']).'>')])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$first}/Filter[/FlateDecode/ASCIIHexDecode]", (string) gzcompress(bin2hex($limpo['dados'])))])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$first}/Filter/FlateDecode/DecodeParms<</Predictor 12/Columns 4>>", (string) gzcompress($limpo['dados']))])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$first}/Filter/FlateDecode", pdfaBinario(500))])))->toBe('pdf_auto_action')
        // "Zip bomb": 21 MB de espaços cabem em ~20 KB comprimidos.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream("/Type/ObjStm/N 1/First {$first}/Filter/FlateDecode", (string) gzcompress($limpo['dados'].str_repeat(' ', 21 * 1024 * 1024)))])))->toBe('pdf_auto_action');
});

it('nomes escritos em hexadecimal (/J#53, /#4A#53, /Java#53cript, /Open#41ction): recusados', function (): void {
    expect(pdfaMotivo(pdfaDe([4 => '<</S/JavaScript/J#53(app.alert(1))>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</S/Java#53cript/#4A#53 5 0 R>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([4 => '<</#4a#53 5 0 R>>'])))->toBe('pdf_auto_action')
        ->and(pdfaMotivo(pdfaDe([], '/Open#41ction 4 0 R')))->toBe('pdf_auto_action');
});

it('ação escondida em volta de um stream: recusada', function (): void {
    $acao = "9 0 obj\n<</S/JavaScript/JS(app.alert(1))>>\nendobj\n";

    // /Length curto: o leitor tolerante lê o objeto depois dele.
    expect(pdfaMotivo(pdfaDe([4 => "<</Length 10>>\nstream\n".pdfaBinario(10)."\n".$acao.'endstream'])))->toBe('pdf_auto_action')
        // /Length longo, com um "endstream" falso no meio dos dados.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream('', pdfaBinario(10)."\nendstream\nendobj\n".$acao)])))->toBe('pdf_auto_action')
        // Sem /Length nenhum.
        ->and(pdfaMotivo(pdfaDe([4 => "<<>>\nstream\n".pdfaBinario(10).'/JS '.pdfaBinario(10)."\nendstream"])))->toBe('pdf_auto_action')
        // "stream" escrito dentro de uma string, com o /Length calculado para
        // "pular" a ação: a string não abre stream nenhum.
        ->and(pdfaMotivo(pdfaDe([4 => '<</Length 40/T(>> stream'."\n".') /S/JavaScript/JS(app.alert(1)) /F(x)>>'."\nendstream"])))->toBe('pdf_auto_action')
        // Cabeçalho de objeto dentro dos dados, com o /Length certo.
        ->and(pdfaMotivo(pdfaDe([4 => pdfaStream('', pdfaBinario(10)."\n".$acao)])))->toBe('pdf_auto_action');
});
