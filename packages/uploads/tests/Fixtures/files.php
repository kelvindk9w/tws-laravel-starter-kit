<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Random\Engine\Mt19937;
use Random\Randomizer;

// =============================================================================
// Fixtures programáticas da suíte do pacote — geradas em bytes,
// sem arquivos binários no repositório. Cada gerador produz o MÍNIMO
// necessário para o finfo classificar o conteúdo (magic bytes reais).
// =============================================================================

/**
 * PDF mínimo válido (header %PDF- + estrutura básica + %%EOF).
 */
function fixtureBytesPdf(): string
{
    return "%PDF-1.4\n"
        ."1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n"
        ."2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n"
        ."3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>\nendobj\n"
        ."trailer\n<</Root 1 0 R>>\n%%EOF";
}

/**
 * PDF válido, porém com JavaScript embutido (/S/JavaScript + /JS) e ação
 * automática (/OpenAction) — política: REJEITADO.
 */
function fixtureBytesPdfComJavaScript(): string
{
    return "%PDF-1.4\n"
        ."1 0 obj\n<</Type/Catalog/Pages 2 0 R/OpenAction 4 0 R>>\nendobj\n"
        ."2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n"
        ."3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>\nendobj\n"
        ."4 0 obj\n<</S/JavaScript/JS (app.alert('x'))>>\nendobj\n"
        ."trailer\n<</Root 1 0 R>>\n%%EOF";
}

/**
 * PNG real 1×1 (transparente), decodificável pela GD.
 */
function fixtureBytesPng(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        true,
    );
}

/**
 * Cabeçalho ELF falso (executável Linux) — magic bytes \x7fELF.
 */
function fixtureBytesElf(): string
{
    return "\x7fELF\x02\x01\x01\x00".random_bytes(64);
}

/**
 * Cria um UploadedFile de teste com CONTEÚDO REAL (a validação de segurança
 * lê os bytes do disco — UploadedFile::fake() não serve aqui).
 */
function fixtureArquivoEnviado(string $bytes, string $name): UploadedFile
{
    $tmp = (string) tempnam(sys_get_temp_dir(), 'upl_');
    file_put_contents($tmp, $bytes);

    return new UploadedFile($tmp, $name, null, null, true);
}

/**
 * PNG real de largura×altura gerado em runtime pela GD.
 */
function fixtureBytesPngDe(int $largura, int $altura): string
{
    $image = imagecreatetruecolor($largura, $altura);
    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * JPEG real de largura×altura com RUÍDO (pixels pseudoaleatórios de semente
 * fixa: o mesmo arquivo a cada execução). Ruído não comprime: os dados do
 * JPEG se comportam como bytes aleatórios, como os de uma foto real — é onde
 * marcadores curtos de 3 bytes (<?=, #!/) aparecem por acaso.
 */
function fixtureBytesJpegComRuido(int $largura, int $altura, int $semente, int $qualidade = 95): string
{
    $pixels = (new Randomizer(new Mt19937($semente)))->getBytes($largura * $altura * 3);

    // BMP de 24 bits (linhas alinhadas em 4 bytes), que a GD lê de uma string.
    $linha = $largura * 3;
    $preenchimento = str_repeat("\0", (4 - $linha % 4) % 4);
    $dados = '';

    for ($y = 0; $y < $altura; $y++) {
        $dados .= substr($pixels, $y * $linha, $linha).$preenchimento;
    }

    $bmp = 'BM'.pack('VvvV', 54 + strlen($dados), 0, 0, 54)
        .pack('VVVvvVVVVVV', 40, $largura, $altura, 1, 24, 0, strlen($dados), 2835, 2835, 0, 0)
        .$dados;

    $image = imagecreatefromstring($bmp);
    ob_start();
    imagejpeg($image, null, $qualidade);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

/**
 * O JPEG com um segmento de comentário (COM, 0xFFFE) contendo $texto logo
 * depois do SOI, precedido de um comentário de enchimento: o $texto começa
 * DEPOIS de $depoisDoByte (0 = logo no começo). O arquivo continua um JPEG
 * válido (o finfo e a GD ignoram comentários).
 */
function fixtureJpegComComentario(string $jpeg, string $texto, int $depoisDoByte = 0): string
{
    $segmento = static fn (string $conteudo): string => "\xFF\xFE".pack('n', strlen($conteudo) + 2).$conteudo;

    $enchimento = $depoisDoByte > 0 ? $segmento(str_repeat('A', $depoisDoByte)) : '';

    return substr($jpeg, 0, 2).$enchimento.$segmento($texto).substr($jpeg, 2);
}

/**
 * PDF válido com um stream BINÁRIO (como o de uma imagem ou fonte embutida)
 * que traz $miolo depois de $depoisDoByte bytes de dados binários. Os bytes
 * de enchimento ficam em 0x80–0xFF: nunca formam '/', '<' nem um token PDF.
 */
function fixtureBytesPdfComStreamBinario(string $miolo, int $depoisDoByte = 2048): string
{
    $binario = '';

    for ($i = 0; $i < $depoisDoByte; $i++) {
        $binario .= chr(0x80 + ($i * 37) % 0x80);
    }

    $stream = $binario.$miolo.$binario;

    return "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"
        ."1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n"
        ."2 0 obj\n<</Type/Pages/Kids[3 0 R]/Count 1>>\nendobj\n"
        ."3 0 obj\n<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</XObject<</Im1 4 0 R>>>>>>\nendobj\n"
        ."4 0 obj\n<</Type/XObject/Subtype/Image/Width 1/Height 1/BitsPerComponent 8/ColorSpace/DeviceGray/Length ".strlen($stream).">>\nstream\n"
        .$stream
        ."\nendstream\nendobj\n"
        ."trailer\n<</Root 1 0 R>>\n%%EOF";
}
