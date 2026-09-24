<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Support\DemoSurface;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\ConnectionEstablished;
use PDO;
use Throwable;

/**
 * Mantém o gatilho das contas demo alinhado com o modo demo DA APLICAÇÃO.
 *
 * O PROBLEMA: o gatilho do PostgreSQL (DemoAccountTrigger) é instalado ou
 * removido conforme o modo demo no momento da migration/seed. Uma base que
 * teve o modo demo ligado e passa a rodar com ele desligado continua com o
 * gatilho: o model já permite alterar e apagar as contas demo (a regra
 * consulta o DemoSurface), mas o banco segue recusando — e o `migrate` do
 * deploy não resolve, porque as migrations que instalam o gatilho já rodaram.
 *
 * A DECISÃO: a aplicação é a fonte da verdade. Com o modo demo DESLIGADO,
 * toda conexão da aplicação com o PostgreSQL desliga a proteção na própria
 * sessão, com a MESMA flag que o gatilho consulta e que
 * DemoAccountGuard::withoutProtection() usa (`tws.demo_guard`). Com o modo
 * demo LIGADO, nada é feito: a sessão nasce sem a flag, e o gatilho trata
 * ausência como proteção ligada.
 *
 * POR QUE NÃO ABRE BRECHA COM O MODO DEMO LIGADO: a decisão é tomada no
 * instante em que a conexão física é aberta, a partir do DemoSurface — o
 * mesmo critério da camada de model (e com o mesmo fail-closed de produção).
 * Nenhuma entrada de usuário chega aqui, e o valor gravado é uma constante.
 * Quem já consegue definir variável de sessão no banco já contornava o
 * gatilho antes (é o escape documentado em DemoAccountTrigger).
 *
 * POR QUE NA ABERTURA DA CONEXÃO FÍSICA, E NÃO NO EVENTO: o Laravel abre o
 * PDO de forma preguiçosa — o evento ConnectionEstablished dispara quando a
 * conexão é CONFIGURADA, antes de existir socket. Rodar SQL no evento
 * forçaria abrir o banco em todo processo que só pergunta o driver (inclusive
 * `composer install` e `config:cache`, onde pode nem haver banco). Por isso o
 * evento só embrulha o abridor do PDO: quando o PDO nascer, a primeira coisa
 * que ele executa é o `set_config`.
 *
 * RECONEXÃO: conexão perdida, `DB::reconnect()` e `DB::purge()` seguido de
 * uso passam pelo DatabaseManager, que dispara o ConnectionEstablished de
 * novo com um abridor novo — e ele é embrulhado de novo. Cada sessão física
 * do PostgreSQL recebe a flag uma vez, na abertura.
 *
 * LIMITE (pooler em modo transação): com PgBouncer em `pool_mode=transaction`
 * a variável de sessão não acompanha a aplicação de uma transação para a
 * próxima. Aí vale o caminho de sempre: com o modo demo desligado, rodar
 * `DemoAccountTrigger::install()` uma vez remove o gatilho do banco.
 */
final class DemoAccountSession
{
    /**
     * Registra o alinhamento em toda conexão que o DatabaseManager abrir a
     * partir de agora, e nas que ele já tiver configurado.
     */
    public static function register(Dispatcher $events, DatabaseManager $db): void
    {
        $events->listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            self::prepare($event->connection);
        });

        foreach ($db->getConnections() as $connection) {
            self::prepare($connection);
        }
    }

    /**
     * Valor da flag de sessão que corresponde ao estado da aplicação: `off`
     * com o modo demo desligado, `on` com ele ligado.
     */
    public static function baseline(): string
    {
        return DemoSurface::loginEnabled() ? 'on' : 'off';
    }

    /**
     * Embrulha os abridores de PDO de escrita da conexão (o de leitura não
     * importa: o gatilho só dispara em UPDATE, DELETE e TRUNCATE).
     */
    public static function prepare(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $pdo = $connection->getRawPdo();

        if ($pdo instanceof Closure) {
            // setPdo zera o contador de transações; aqui a conexão acabou de
            // ser configurada (ou reconectada), então ele já é zero.
            $connection->setPdo(self::wrap($pdo));
        } elseif ($pdo instanceof PDO) {
            self::apply($pdo);
        }

        $direct = $connection->getRawDirectPdo();

        if ($direct instanceof Closure) {
            $connection->setDirectPdo(self::wrap($direct));
        } elseif ($direct instanceof PDO && $direct !== $pdo) {
            self::apply($direct);
        }
    }

    /**
     * @param  Closure(): PDO  $opener
     * @return Closure(): PDO
     */
    private static function wrap(Closure $opener): Closure
    {
        return function () use ($opener): PDO {
            $pdo = $opener();

            self::apply($pdo);

            return $pdo;
        };
    }

    /**
     * Com o modo demo desligado, desliga a proteção nesta sessão. Com ele
     * ligado, não toca na sessão.
     */
    private static function apply(PDO $pdo): void
    {
        if (self::baseline() === 'on') {
            return;
        }

        try {
            $pdo->exec("SELECT set_config('".DemoAccountGuard::DATABASE_FLAG."', 'off', false)");
        } catch (Throwable) {
            // Se falhar, a sessão fica como sempre foi: com a proteção ligada.
            // O pior caso é o banco seguir recusando alterar a conta demo —
            // nunca o contrário.
        }
    }
}
