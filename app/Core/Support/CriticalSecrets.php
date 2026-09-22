<?php

declare(strict_types=1);

namespace App\Core\Support;

use App\Core\Support\Exceptions\MissingApplicationKeyException;
use Illuminate\Support\Facades\Log;

/**
 * A REGRA dos segredos que NÃO podem ser inventados, em um lugar só.
 *
 * PROBLEMA: um starter kit precisa subir na primeira tentativa, e o jeito de
 * conseguir isso é dar valor padrão para tudo. Só que existe uma classe de
 * variável para a qual valor padrão é a própria falha de segurança: as que
 * carregam segredo. Dois sintomas do mesmo bug foram encontrados no kit:
 *
 *   CHAVE GERADA NA SUBIDA — o entrypoint de produção gerava a APP_KEY quando
 *   ela vinha vazia. Como a imagem é a mesma para app, migrate, horizon e
 *   scheduler, cada container ficava com uma chave DIFERENTE; e como a chave
 *   era escrita no `.env` da camada gravável, ela também não sobrevivia ao
 *   restart. Nada disso dava erro: o dado simplesmente deixava de
 *   descriptografar.
 *
 *   SENHA PADRÃO FUNCIONAL — o docker-compose.prod.yml oferecia
 *   `troque-esta-senha` como fallback de POSTGRES_PASSWORD e REDIS_PASSWORD.
 *   Uma senha padrão que FUNCIONA é a mesma armadilha da flag com padrão
 *   ligado: quem não leu o comentário sobe em produção com a senha que está
 *   publicada no repositório, e nada avisa.
 *
 * SOLUÇÃO EM DUAS CAMADAS, porque as duas falham por motivos diferentes:
 *
 *   NO SHELL (docker/php/entrypoint-prod.sh) — ausência da APP_KEY em produção
 *   para a subida com código 78. É a camada que age ANTES de o php-fpm existir.
 *
 *   NO COMPOSE (docker-compose.prod.yml) — os fallbacks funcionais de senha
 *   viraram `${VAR:?...}`: sem a variável, o Compose se recusa a resolver o
 *   arquivo. A senha padrão deixou de ser inventável no ponto onde era
 *   inventada.
 *
 *   AQUI, no boot da aplicação — o que o shell e o compose não conseguem ver:
 *   chave PRESENTE mas de placeholder, e segredo de infraestrutura com valor de
 *   placeholder numa instalação que não subiu pelo compose do kit.
 *
 * DUAS RESPOSTAS DIFERENTES, e o critério que as separa é "existe valor seguro
 * a forçar?":
 *
 *   CHAVE DA APLICAÇÃO → RECUSA o boot (exceção). Não há valor seguro a
 *   forçar: gerar uma é o bug, e seguir com a pública é vazar tudo em silêncio.
 *
 *   SEGREDOS DE INFRAESTRUTURA → AVISO alto no log, a cada boot, sem recusar.
 *   Não é leniência, é proporcionalidade: se o Postgres já foi provisionado com
 *   aquela senha, derrubar a aplicação não troca a senha — só troca um problema
 *   de segurança por uma indisponibilidade, mantendo o problema de segurança. O
 *   remédio de verdade é a rotação, que é um procedimento com janela, e o log
 *   recorrente é o que impede que a instalação esqueça que o deve. A causa raiz
 *   (o fallback funcional) já está fechada no compose.
 *
 * NADA HARDCODED: o vocabulário de placeholders é configurável em
 * `security.secrets.placeholders`, porque cada instalação tem os seus valores
 * históricos de "depois eu troco". Já a LISTA de segredos inspecionados mora
 * aqui, em código documentado, do mesmo jeito que a lógica do SafeRedirect: é
 * estrutura do kit, não ajuste de operação.
 */
final class CriticalSecrets
{
    /**
     * Segredos de infraestrutura inspecionados: nome da variável de ambiente
     * (é por ele que o operador conhece o valor) => caminho de configuração.
     *
     * Lidos de configuração, e não de `env()` direto, para continuar
     * funcionando com o config cacheado do deploy.
     *
     * @var array<string, string>
     */
    private const INFRASTRUCTURE_SECRETS = [
        'DB_PASSWORD' => 'database.connections.pgsql.password',
        'REDIS_PASSWORD' => 'database.redis.default.password',
        'BACKUP_ARCHIVE_PASSWORD' => 'backup.backup.password',
        'API_KEYS_HASH_PEPPER' => 'api_keys.hash_pepper',
        'AWS_SECRET_ACCESS_KEY' => 'filesystems.disks.s3.secret',
    ];

    /**
     * Portão chamado no boot, em produção.
     *
     * Recusa quando a chave da aplicação não é utilizável; avisa quando algum
     * segredo de infraestrutura está com valor de placeholder.
     *
     * @throws MissingApplicationKeyException
     */
    public static function guard(): void
    {
        $key = self::applicationKey();

        if ($key === '') {
            throw MissingApplicationKeyException::missing();
        }

        if (self::isPlaceholder($key)) {
            throw MissingApplicationKeyException::placeholder();
        }

        $withPlaceholder = self::infrastructureSecretsWithPlaceholder();

        if ($withPlaceholder !== []) {
            Log::warning(
                'Segredos com valor de exemplo/placeholder em APP_ENV=production: '
                .implode(', ', $withPlaceholder)
                .'. Estes valores estão publicados na documentação do kit — quem alcançar a rede '
                .'interna já os conhece. A aplicação NÃO foi recusada porque derrubá-la não troca '
                .'a senha: o remédio é rotacionar o segredo no serviço correspondente e entregar o '
                .'novo valor pelo ambiente. Este aviso volta a cada boot até que isso aconteça.'
            );
        }
    }

    /**
     * A chave de aplicação é utilizável nesta instalação?
     *
     * Utilizável = existe e não é um valor conhecido. Note que ela pode ser
     * utilizável e AINDA ASSIM estar errada (chave própria, mas diferente da
     * que criptografou o dado): isso nenhum guard descobre de fora, e é por
     * isso que a documentação insiste em APP_PREVIOUS_KEYS.
     */
    public static function applicationKeyUsable(): bool
    {
        $key = self::applicationKey();

        return $key !== '' && ! self::isPlaceholder($key);
    }

    /**
     * Nomes das variáveis de ambiente cujos segredos estão com valor de
     * placeholder. Segredo VAZIO não entra: ausência é uma decisão possível
     * (não há bucket R2, não há backup configurado), enquanto um valor público
     * é sempre um esquecimento.
     *
     * @return list<string>
     */
    public static function infrastructureSecretsWithPlaceholder(): array
    {
        $found = [];

        foreach (self::INFRASTRUCTURE_SECRETS as $variable => $path) {
            $value = config($path);

            if (is_string($value) && $value !== '' && self::isPlaceholder($value)) {
                $found[] = $variable;
            }
        }

        return $found;
    }

    /**
     * Chave da aplicação como string normalizada (vazia quando ausente).
     *
     * `base64:` sozinho conta como ausente: é o que sobra quando alguém apaga
     * o valor e deixa o prefixo.
     */
    private static function applicationKey(): string
    {
        $key = trim((string) config('app.key'));

        return $key === 'base64:' ? '' : $key;
    }

    /**
     * O valor é um segredo de fachada?
     *
     * Duas formas de reconhecer, porque placeholder aparece de dois jeitos:
     *
     *   VOCABULÁRIO — o valor está na lista de
     *   `security.secrets.placeholders` (`troque-esta-senha` e companhia).
     *   Comparação sem diferenciar maiúsculas e ignorando o prefixo `base64:`,
     *   que só diz como o valor foi codificado, não o que ele é.
     *
     *   DEGENERADO — o valor decodifica para uma sequência de bytes TODOS
     *   IGUAIS (`base64:AAAA…`, o "preenchi com qualquer coisa"). É o
     *   placeholder que não tem nome para entrar em lista nenhuma, e a chance
     *   de uma chave aleatória de verdade cair aqui é desprezível.
     */
    private static function isPlaceholder(string $value): bool
    {
        $bare = str_starts_with($value, 'base64:')
            ? substr($value, strlen('base64:'))
            : $value;

        /** @var list<string> $vocabulary */
        $vocabulary = (array) config('security.secrets.placeholders', []);

        foreach ($vocabulary as $placeholder) {
            $placeholder = trim((string) $placeholder);

            if ($placeholder === '') {
                continue;
            }

            if (strcasecmp($bare, $placeholder) === 0 || strcasecmp($value, $placeholder) === 0) {
                return true;
            }
        }

        return self::isDegenerate($bare);
    }

    /**
     * Sequência de bytes todos iguais (depois de decodificar o base64, quando
     * o valor é base64 válido). Valores curtíssimos não são avaliados aqui:
     * eles ou estão no vocabulário, ou são recusados pelo próprio Laravel por
     * não terem o tamanho da cifra.
     */
    private static function isDegenerate(string $bare): bool
    {
        $decoded = base64_decode($bare, true);
        $bytes = is_string($decoded) && $decoded !== '' ? $decoded : $bare;

        if (strlen($bytes) < 8) {
            return false;
        }

        return count(array_unique(str_split($bytes))) === 1;
    }
}
