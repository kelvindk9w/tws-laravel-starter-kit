<?php

declare(strict_types=1);

namespace App\Core\Showcase\Support;

use Illuminate\Support\Str;

/**
 * Trecho NEUTRALIZADO de uma submissão bloqueada (vitrine de segurança).
 *
 * POR QUE ISSO EXISTE. Até aqui a listagem do super admin mostrava o
 * payload literal — escapado, portanto inerte, mas legível. Inerte não é
 * o mesmo que inofensivo: uma lista de tentativas de ataque exibidas por
 * extenso é um catálogo pronto para copiar e colar, e o operador que
 * triagem essas mensagens todo dia é justamente quem tem acesso ao painel.
 * A listagem passou a mostrar o TIPO do ataque e um trecho sem dentes; o
 * payload íntegro ficou na tela de detalhe, rotulado como evidência.
 *
 * O QUE NEUTRALIZAR SIGNIFICA AQUI (nesta ordem, e a ordem importa):
 *
 * 1. `strip_tags` remove a marcação;
 * 2. as entidades HTML são decodificadas — `&lt;script&gt;` escondido no
 *    texto voltaria a ser marcação numa exibição futura;
 * 3. `strip_tags` de novo, sobre o texto já decodificado (é aqui que morre
 *    o payload duplamente codificado, que passaria batido no passo 1);
 * 4. TODO `<` e `>` remanescente cai — depois deste ponto a string não tem
 *    como voltar a ser uma tag, aconteça o que acontecer a jusante;
 * 5. espaços em branco colapsam e o texto é truncado.
 *
 * A gravação no banco continua CRUA (auditoria — ADR-004/005): quem
 * neutraliza é a exibição, nunca o registro.
 */
final class SubmissionExcerpt
{
    /**
     * Teto do trecho da listagem (caracteres). Curto de propósito: a
     * coluna informa que houve tentativa, não conta a história dela.
     */
    public const MAX_LENGTH = 60;

    public static function neutralize(?string $raw, int $limit = self::MAX_LENGTH): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $text = strip_tags($raw);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        $text = strip_tags($text);

        // Rede final: sem `<` e `>` a string não volta a ser marcação.
        $text = (string) preg_replace('/[<>]+/u', ' ', $text);

        // Null bytes e caracteres de controle fora (quebram o layout e são
        // metade dos payloads de null_byte).
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        return Str::limit($text, $limit);
    }
}
