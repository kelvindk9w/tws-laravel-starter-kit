<?php

declare(strict_types=1);

namespace Twstec\Kit\Uploads\Services;

use finfo;
use Twstec\Kit\Uploads\Exceptions\UploadRejectedException;

/**
 * NÚCLEO da validação de segurança de uploads (conteúdo real, nunca extensão ou MIME declarado).
 *
 * Política (lei): o arquivo é o que os MAGIC BYTES dizem, nunca a extensão
 * declarada. Pipeline, nesta ordem — qualquer suspeita = REJEITADO:
 *
 *   1. Executável disfarçado (PE/ELF/shebang) → fora, antes de tudo.
 *   2. MIME REAL via finfo (conteúdo) contra a allowlist por tipo (config).
 *      PDF é só PDF; imagem é só imagem. Qualquer outro MIME → fora.
 *   3. Extensão declarada divergente do conteúdo → fora.
 *   4. Conteúdo varrido por marcadores de script → fora (polyglot embutido).
 *      Os LONGOS (<?php, <script) no arquivo inteiro, em todo tipo. Os
 *      CURTOS (<?=, #!/) no arquivo inteiro só nos tipos de TEXTO; nos
 *      binários, só no cabeçalho (primeiros 1024 bytes) — em dados
 *      comprimidos (JPEG, PNG, PDF, MP4, OGG, ZIP) 3 bytes fixos aparecem
 *      por acaso, e varrer o arquivo inteiro recusava fotos legítimas.
 *   5. PDF com JavaScript, ações automáticas ou lançamento de programa
 *      (/JS, /JavaScript, /OpenAction, /AA, /Launch) → fora. Procurados só
 *      onde o leitor de PDF lê objetos: fora dos dados de stream (onde fotos
 *      comprimidas formavam /JS e /AA por acaso), com nomes #xx
 *      decodificados e dentro dos streams de objetos (/ObjStm),
 *      descomprimidos. Stream de objetos que não dá para ler → fora.
 *      PDF com ARQUIVO ANEXADO, ou CIFRADO (com senha) com stream de
 *      objetos, que não dá para conferir → fora com motivo próprio
 *      (pdf_attachment_or_password), para a pessoa saber o que fazer.
 *      Política do dono: suspeita = não aceita.
 *   6. Imagens: decodificadas e RE-ENCODADAS via GD (segunda camada — mesmo
 *      que um payload desconhecido escape da varredura, ele não sobrevive à
 *      re-geração dos pixels). Imagem que a GD não decodifica → fora.
 *
 * O retorno traz o CONTEÚDO FINAL a persistir (re-encodado, no caso de
 * imagens), o MIME real e a extensão canônica derivada do MIME — nunca do
 * nome original.
 */
final class FileSecurityValidator
{
    /**
     * Extensões declaradas aceitas por extensão canônica (ex.: o canônico é
     * 'jpg', mas 'jpeg' declarado é legítimo para image/jpeg).
     *
     * @var array<string, list<string>>
     */
    private const EXTENSION_ALIASES = [
        'jpg' => ['jpg', 'jpeg'],
    ];

    /**
     * Marcadores LONGOS de script embutido: procurados no arquivo inteiro, em
     * todo tipo (varredura case-insensitive). Em dados aleatórios a chance de
     * aparecerem por acaso é desprezível (<?php: ~7×10⁻¹² por posição;
     * <script: ~9×10⁻¹⁶), então valem como defesa contra polyglot em qualquer
     * posição. Achou = suspeita = rejeitado.
     *
     * @var list<string>
     */
    private const SCRIPT_MARKERS_LONG = ['<?php', '<script'];

    /**
     * Marcadores CURTOS (3 bytes): procurados no arquivo inteiro só nos tipos
     * de TEXTO (MIME_TEXT_*); nos binários, só nos primeiros
     * SHORT_MARKERS_BINARY_WINDOW bytes. Em dados comprimidos uma sequência
     * fixa de 3 bytes aparece, em média, uma vez a cada ~16,7 milhões de
     * posições: com os dois marcadores, uma foto de 2 MB tinha perto de 1
     * chance em 4 de ser recusada por engano (na medição com fotos reais,
     * 9 de 20). Num binário, o disfarce que engana um interpretador lido do
     * começo precisa estar no cabeçalho — é ali que a procura continua.
     *
     * @var list<string>
     */
    private const SCRIPT_MARKERS_SHORT = ['<?=', '#!/'];

    /**
     * Quantos bytes do começo de um binário recebem a procura dos marcadores
     * curtos.
     */
    private const SHORT_MARKERS_BINARY_WINDOW = 1024;

    /**
     * MIMEs de TEXTO, além de todo `text/*` e de todo `+xml`/`+json`: neles
     * os marcadores curtos são procurados no arquivo inteiro.
     *
     * @var list<string>
     */
    private const MIME_TEXT_EXACT = [
        'application/json',
        'application/xml',
        'application/csv',
        'application/javascript',
        'application/x-httpd-php',
        'application/x-php',
        'application/x-sh',
        'image/svg+xml',
    ];

    /**
     * Tokens de dicionário PDF que indicam JavaScript, ações automáticas ou
     * lançamento de programa (motivo pdf_auto_action). Casados com fronteira
     * de delimitador PDF (espaço, '/', '()', '<>', '[]', '{}', '%' ou fim)
     * para não confundir com nomes mais longos.
     *
     * @var list<string>
     */
    private const PDF_DANGEROUS_TOKENS = ['/JavaScript', '/JS', '/OpenAction', '/AA', '/Launch'];

    /**
     * Tokens de dicionário PDF que indicam ARQUIVO ANEXADO (motivo
     * pdf_attachment_or_password): o arquivo em si (/EmbeddedFile, /EF), a
     * lista de anexos do documento (/EmbeddedFiles) e o anexo numa página
     * (/FileAttachment).
     *
     * @var list<string>
     */
    private const PDF_ATTACHMENT_TOKENS = ['/EmbeddedFile', '/EmbeddedFiles', '/EF', '/FileAttachment'];

    /**
     * Fronteira de delimitador PDF depois de um nome.
     */
    private const PDF_NAME_END = '(?=[\s\(\)<>\[\]\{\}\/%]|$)';

    /**
     * Filtros de stream que a varredura não lê. Stream de objetos com um
     * deles (sozinho ou encadeado com o FlateDecode) = não dá para conferir
     * = rejeitado.
     */
    private const PDF_UNREADABLE_FILTERS = '/\/(?:ASCIIHexDecode|AHx|ASCII85Decode|A85|LZWDecode|LZW|RunLengthDecode|RL|CCITTFaxDecode|CCF|JBIG2Decode|DCTDecode|DCT|JPXDecode|Crypt)(?=[\s\(\)<>\[\]\{\}\/%]|$)/';

    /**
     * Teto do conteúdo DESCOMPRIMIDO de todos os streams de objetos de um PDF
     * (proteção contra "zip bomb"). Acima dele = rejeitado.
     */
    private const PDF_OBJECT_STREAMS_MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @param  string  $content  Bytes brutos do arquivo.
     * @param  string  $declaredExtension  Extensão do nome original (lowercase, sem ponto).
     * @param  list<string>  $allowedTypes  Chaves de config('uploads.types').
     * @return array{mime: string, extension: string, content: string}
     *
     * @throws UploadRejectedException Qualquer divergência ou suspeita.
     */
    public function validate(string $content, string $declaredExtension, array $allowedTypes): array
    {
        if ($content === '') {
            $this->reject('empty');
        }

        $this->ensureNotExecutable($content);

        $mime = $this->detectMime($content);

        [$type, $typeConfig, $canonicalExtension] = $this->matchAllowedType($mime, $allowedTypes);

        $this->ensureExtensionMatches($declaredExtension, $canonicalExtension);

        $this->ensureNoEmbeddedScript($content, $mime);

        if ($type === 'pdf') {
            $this->ensurePdfHasNoAutoActions($content);
        }

        if ($type === 'image') {
            $content = $this->reencodeImage($content, $mime, $typeConfig);
        }

        return [
            'mime' => $mime,
            'extension' => $canonicalExtension,
            'content' => $content,
        ];
    }

    /**
     * 1. Magic bytes de executável/script no início do arquivo.
     *    Redundante com a allowlist de MIME de propósito (defesa em profundidade:
     *    se a allowlist for mal configurada um dia, isto continua barrando).
     */
    private function ensureNotExecutable(string $content): void
    {
        if (
            str_starts_with($content, 'MZ')              // PE (Windows .exe/.dll)
            || str_starts_with($content, "\x7fELF")       // ELF (Linux)
            || str_starts_with($content, '#!')            // shebang de script
            || str_starts_with($content, "\xCA\xFE\xBA\xBE") // Java class / Mach-O fat
        ) {
            $this->reject('executable');
        }
    }

    /**
     * 2. MIME real pelo conteúdo (magic bytes), nunca pela extensão.
     */
    private function detectMime(string $content): string
    {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if (! is_string($detected) || $detected === '') {
            $this->reject('mime_undetectable');
        }

        return $detected;
    }

    /**
     * 2b. O MIME real precisa estar na allowlist de algum tipo permitido.
     *     PDF é só PDF; imagem é só imagem; o resto não entra.
     *
     * @param  list<string>  $allowedTypes
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function matchAllowedType(string $mime, array $allowedTypes): array
    {
        /** @var array<string, array<string, mixed>> $types */
        $types = (array) config('uploads.types', []);

        foreach ($allowedTypes as $type) {
            $typeConfig = $types[$type] ?? null;

            if (! is_array($typeConfig)) {
                continue;
            }

            /** @var array<string, string> $mimes */
            $mimes = (array) ($typeConfig['mimes'] ?? []);

            if (array_key_exists($mime, $mimes)) {
                return [$type, $typeConfig, $mimes[$mime]];
            }
        }

        $this->reject('mime_not_allowed');
    }

    /**
     * 3. A extensão declarada no nome original precisa ser compatível com o
     *    conteúdo REAL. Divergência = tentativa de disfarce = rejeitado.
     */
    private function ensureExtensionMatches(string $declaredExtension, string $canonicalExtension): void
    {
        $accepted = self::EXTENSION_ALIASES[$canonicalExtension] ?? [$canonicalExtension];

        if (! in_array($declaredExtension, $accepted, true)) {
            $this->reject('extension_mismatch');
        }
    }

    /**
     * 4. Marcadores de script embutido (polyglot: começa como imagem/PDF,
     *    carrega código executável junto). Os longos em qualquer posição de
     *    qualquer tipo; os curtos em qualquer posição só no texto e, no
     *    binário, só no cabeçalho (ver SCRIPT_MARKERS_SHORT).
     *
     *    Imagem: a re-geração pela GD (passo 6) descarta o arquivo original
     *    inteiro, então a procura no binário é defesa em profundidade.
     *    PDF: o passo 5 continua barrando JavaScript e ações automáticas.
     */
    private function ensureNoEmbeddedScript(string $content, string $mime): void
    {
        $haystack = strtolower($content);

        foreach (self::SCRIPT_MARKERS_LONG as $marker) {
            if (str_contains($haystack, $marker)) {
                $this->reject('embedded_script');
            }
        }

        $shortHaystack = $this->isTextMime($mime)
            ? $haystack
            : substr($haystack, 0, self::SHORT_MARKERS_BINARY_WINDOW);

        foreach (self::SCRIPT_MARKERS_SHORT as $marker) {
            if (str_contains($shortHaystack, $marker)) {
                $this->reject('embedded_script');
            }
        }
    }

    /**
     * MIME de texto: `text/*`, os sufixos estruturados `+xml`/`+json` e a
     * lista MIME_TEXT_EXACT. O resto é tratado como binário.
     */
    private function isTextMime(string $mime): bool
    {
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));

        return str_starts_with($mime, 'text/')
            || str_ends_with($mime, '+xml')
            || str_ends_with($mime, '+json')
            || in_array($mime, self::MIME_TEXT_EXACT, true);
    }

    /**
     * 5. PDF com JavaScript embutido, ações automáticas ou lançamento de
     *    programa = REJEITADO (pdf_auto_action); com arquivo anexado, ou
     *    cifrado com stream de objetos = REJEITADO com motivo próprio
     *    (pdf_attachment_or_password). Política: suspeita = não aceita. A
     *    procura é nos OBJETOS do PDF (pdfObjectText), não nos dados
     *    binários dos streams.
     */
    private function ensurePdfHasNoAutoActions(string $content): void
    {
        // Cifrado (com senha, mesmo a de dono só): os streams de objetos vêm
        // cifrados e não dá para conferir o que eles guardam.
        $encrypted = preg_match('/\/Encrypt'.self::PDF_NAME_END.'/', $content) === 1;

        $objects = $this->pdfObjectText($content, $encrypted);

        foreach ([...self::PDF_DANGEROUS_TOKENS, ...self::PDF_ATTACHMENT_TOKENS] as $token) {
            // Fronteira de delimitador PDF: o token só casa como objeto de
            // dicionário, não como substring de um nome maior (ex.: /AAbc).
            if (preg_match('/'.preg_quote($token, '/').self::PDF_NAME_END.'/', $objects) === 1) {
                $this->reject(in_array($token, self::PDF_DANGEROUS_TOKENS, true) ? 'pdf_auto_action' : 'pdf_attachment_or_password');
            }
        }
    }

    /**
     * O texto do PDF que um leitor interpreta como OBJETOS:
     *
     *   - tudo o que está fora dos dados de stream (dicionários, arrays,
     *     trailer). Os dados de um stream (foto, fonte, página comprimida)
     *     ficam de fora: neles /JS e /AA aparecem por acaso. O começo de um
     *     stream é a palavra "stream" depois de um dicionário (">>"), fora de
     *     string e de comentário — "stream" escrito dentro de uma string
     *     não esconde nada;
     *   - os dados de um stream só ficam de fora quando o /Length (direto ou
     *     num objeto inteiro) aponta exatamente para o "endstream" e eles não
     *     trazem outro "endstream" nem um cabeçalho de objeto ("12 0 obj"),
     *     que um leitor tolerante leria como objetos. Senão, entram inteiros;
     *   - os streams de OBJETOS (/Type/ObjStm, ou /First e /N inteiros, que
     *     é o que leitores como o pdf.js conferem), descomprimidos
     *     (FlateDecode, até PDF_OBJECT_STREAMS_MAX_BYTES no total). O que não
     *     dá para ler = rejeitado; num PDF cifrado, nem dá para tentar =
     *     rejeitado com motivo próprio (pdf_attachment_or_password);
     *   - com os nomes escritos em hexadecimal decodificados (/J#53 = /JS).
     */
    private function pdfObjectText(string $content, bool $encrypted): string
    {
        $objects = '';
        $budget = self::PDF_OBJECT_STREAMS_MAX_BYTES;
        $offset = 0;

        while (($stream = $this->nextPdfStream($content, $offset)) !== null) {
            [$keyword, $dataStart] = $stream;

            // O pedaço desde o fim do stream anterior: os objetos sem stream
            // e o dicionário DESTE stream (o único dicionário de stream dele).
            $chunk = $this->decodePdfNames(substr($content, $offset, $keyword - $offset));
            [$dataEnd, $next, $exact] = $this->pdfStreamEnd($content, $dataStart, $chunk);
            $data = substr($content, $dataStart, $dataEnd - $dataStart);

            $objects .= $chunk."\n";

            if (! $exact || str_contains($data, 'endstream') || preg_match('/\d+\s+\d+\s+obj(?![A-Za-z0-9])/', $data) === 1) {
                $objects .= $this->decodePdfNames($data)."\n";
            }

            if ($this->isPdfObjectStream($chunk)) {
                if ($encrypted) {
                    $this->reject('pdf_attachment_or_password');
                }

                $decoded = $this->decodePdfObjectStream($data, $chunk, $budget);
                $budget -= strlen($decoded);
                $objects .= $this->decodePdfNames($decoded)."\n";
            }

            $offset = $next;
        }

        return $objects.$this->decodePdfNames((string) substr($content, $offset));
    }

    /**
     * O próximo começo de stream a partir de $offset: a palavra "stream"
     * seguida de fim de linha, logo depois de um ">>" (só espaço ou
     * comentário no meio), fora de string literal e de comentário.
     *
     * @return array{0: int, 1: int}|null Posição da palavra e do primeiro byte dos dados.
     */
    private function nextPdfStream(string $content, int $offset): ?array
    {
        $size = strlen($content);
        $afterDictionary = false;
        $i = $offset;

        while ($i < $size) {
            $char = $content[$i];

            if ($char === '(') {
                $i = $this->skipPdfString($content, $i);
                $afterDictionary = false;
            } elseif ($char === '%') {
                $i += strcspn($content, "\r\n", $i);
            } elseif ($char === '>' && ($content[$i + 1] ?? '') === '>') {
                $afterDictionary = true;
                $i += 2;
            } elseif (str_contains(" \t\r\n\f\0", $char)) {
                $i++;
            } elseif ($afterDictionary && $char === 's' && substr_compare($content, 'stream', $i, 6) === 0
                && preg_match('/\G(?:\r\n|\n|\r)/', $content, $eol, 0, $i + 6) === 1) {
                return [$i, $i + 6 + strlen($eol[0])];
            } else {
                $afterDictionary = false;
                $i += 1 + strcspn($content, "(%>s \t\r\n\f\0", $i + 1);
            }
        }

        return null;
    }

    /**
     * Pula uma string literal "( ... )" do PDF (parênteses aninhados e
     * escapes com barra invertida). Devolve a posição depois do ")" final
     * (ou o fim do arquivo).
     */
    private function skipPdfString(string $content, int $start): int
    {
        $size = strlen($content);
        $depth = 0;
        $i = $start;

        while ($i < $size) {
            $i += strcspn($content, '()\\', $i);

            if ($i >= $size) {
                break;
            }

            if ($content[$i] === '\\') {
                $i += 2;

                continue;
            }

            $depth += $content[$i] === '(' ? 1 : -1;
            $i++;

            if ($depth === 0) {
                return $i;
            }
        }

        return $size;
    }

    /**
     * Onde terminam os dados do stream que começa em $dataStart, onde a
     * leitura continua e se o fim veio do /Length. Vale o /Length do
     * dicionário (direto, ou o inteiro de um objeto "N G obj ... endobj")
     * quando ele bate com um "endstream" logo depois; senão, o primeiro
     * "endstream" (sem nenhum, o stream vai até o fim do arquivo).
     *
     * @return array{0: int, 1: int, 2: bool}
     */
    private function pdfStreamEnd(string $content, int $dataStart, string $dictionary): array
    {
        $size = strlen($content);
        $length = null;

        if (preg_match_all('/\/Length\s+(\d+)(?:\s+(\d+)\s+R)?/', $dictionary, $lengths, PREG_SET_ORDER) > 0) {
            $last = end($lengths);

            if (($last[2] ?? '') === '') {
                $length = (int) $last[1];
            } elseif (preg_match('/(?<!\d)'.$last[1].'\s+'.$last[2].'\s+obj\s*(\d+)\s*endobj/', $content, $object) === 1) {
                $length = (int) $object[1];
            }
        }

        if ($length !== null && $dataStart + $length <= $size
            && preg_match('/\G\s*endstream/', $content, $end, 0, $dataStart + $length) === 1) {
            return [$dataStart + $length, $dataStart + $length + strlen($end[0]), true];
        }

        $end = strpos($content, 'endstream', $dataStart);

        return $end === false ? [$size, $size, false] : [$end, $end + strlen('endstream'), false];
    }

    /**
     * Stream de objetos: declarado (/Type/ObjStm) ou com o que um leitor
     * confere para tratá-lo como um (/First e /N inteiros diretos).
     */
    private function isPdfObjectStream(string $dictionary): bool
    {
        return preg_match('/\/ObjStm(?=[\s\(\)<>\[\]\{\}\/%]|$)/', $dictionary) === 1
            || (preg_match('/\/First\s+\d+(?!\s+\d+\s+R)/', $dictionary) === 1
                && preg_match('/\/N\s+\d+(?!\s+\d+\s+R)/', $dictionary) === 1);
    }

    /**
     * O conteúdo de um stream de objetos: sem filtro, os próprios bytes; com
     * FlateDecode, descomprimido até o que resta de $budget (zlib). Outro filtro,
     * parâmetros de decodificação (preditor), dado corrompido ou cifrado,
     * ou acima do teto = não dá para conferir = REJEITADO.
     */
    private function decodePdfObjectStream(string $data, string $dictionary, int $budget): string
    {
        if (preg_match(self::PDF_UNREADABLE_FILTERS, $dictionary) === 1
            || preg_match('/\/(?:DecodeParms|DP)(?=[\s\(\)<>\[\]\{\}\/%]|$)/', $dictionary) === 1) {
            $this->reject('pdf_auto_action');
        }

        if (preg_match('/\/(?:FlateDecode|Fl)(?=[\s\(\)<>\[\]\{\}\/%]|$)/', $dictionary) !== 1) {
            if (preg_match('/\/Filter(?=[\s\(\)<>\[\]\{\}\/%]|$)/', $dictionary) === 1) {
                $this->reject('pdf_auto_action');
            }

            return $data;
        }

        $inflate = function_exists('inflate_init') ? @inflate_init(ZLIB_ENCODING_DEFLATE) : false;

        if ($inflate === false) {
            $this->reject('pdf_auto_action');
        }

        // Em pedaços de 1 KB, conferindo o teto a cada um: um pedaço não
        // passa de ~1 MB descomprimido (a razão máxima do deflate é ~1:1032),
        // então a memória nunca vai muito além do teto.
        $decoded = '';

        foreach (str_split($data, 1024) as $piece) {
            $out = @inflate_add($inflate, $piece, ZLIB_SYNC_FLUSH);

            if ($out === false) {
                $this->reject('pdf_auto_action');
            }

            $decoded .= $out;

            if (strlen($decoded) > $budget) {
                $this->reject('pdf_auto_action');
            }

            if (inflate_get_status($inflate) === ZLIB_STREAM_END) {
                break;
            }
        }

        return $decoded;
    }

    /**
     * Nomes PDF com caracteres em hexadecimal (#xx) decodificados — /J#53 é
     * o mesmo nome que /JS para o leitor. Decodifica todo #xx do texto (fora
     * de nome, só deixa a procura mais desconfiada).
     */
    private function decodePdfNames(string $text): string
    {
        if (! str_contains($text, '#')) {
            return $text;
        }

        return (string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $hex): string => chr((int) hexdec($hex[1])), $text);
    }

    /**
     * 6. Re-encode da imagem via GD: decodifica os pixels e re-gera o arquivo
     *    do zero — metadados, comentários e trailing data (onde payloads se
     *    escondem) não sobrevivem. Também PROVA que a imagem é decodificável.
     *    GD indisponível ou falha de decode = falha fechada (rejeita).
     *
     * @param  array<string, mixed>  $typeConfig
     */
    private function reencodeImage(string $content, string $mime, array $typeConfig): string
    {
        $info = @getimagesizefromstring($content);

        if ($info === false) {
            $this->reject('image_undecodable');
        }

        // Teto de pixels antes de decodificar (proteção contra decompression
        // bomb — a GD alocaria largura×altura×4 bytes em memória).
        $maxPixels = (int) ($typeConfig['max_pixels'] ?? 0);

        if ($maxPixels > 0 && ($info[0] * $info[1]) > $maxPixels) {
            $this->reject('image_too_many_pixels');
        }

        if (! function_exists('imagecreatefromstring')) {
            // Falha FECHADA: sem GD não há como reprocessar — não entra.
            $this->reject('image_reencode_unavailable');
        }

        $image = @imagecreatefromstring($content);

        if ($image === false) {
            $this->reject('image_undecodable');
        }

        // Preserva transparência (PNG/WebP com canal alfa).
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $encoded = match ($mime) {
            'image/jpeg' => $this->capture(static fn () => imagejpeg($image, null, 90)),
            'image/png' => $this->capture(static fn () => imagepng($image)),
            'image/webp' => $this->capture(static fn () => imagewebp($image, null, 90)),
            default => null,
        };

        imagedestroy($image);

        if (! is_string($encoded) || $encoded === '') {
            $this->reject('image_reencode_failed');
        }

        return $encoded;
    }

    /**
     * Captura a saída binária de um encoder da GD (imagejpeg/imagepng/...).
     *
     * @return string|null Bytes da imagem re-encodada (null em falha).
     */
    private function capture(callable $encoder): ?string
    {
        ob_start();

        $ok = $encoder();

        $bytes = ob_get_clean();

        return $ok && is_string($bytes) ? $bytes : null;
    }

    /**
     * @throws UploadRejectedException Sempre.
     */
    private function reject(string $reason): never
    {
        throw new UploadRejectedException($reason, __('uploads.rejected.'.$reason));
    }
}
