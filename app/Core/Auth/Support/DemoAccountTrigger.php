<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

use App\Core\Support\DemoSurface;
use Illuminate\Support\Facades\DB;

/**
 * A TERCEIRA camada de proteção das contas demo: um gatilho no PostgreSQL.
 *
 * Por que o banco, se o model já barra? Porque `User::where(...)->delete()`
 * e `User::query()->update([...])` NÃO disparam eventos de model — o
 * Eloquent monta um único DELETE/UPDATE e manda. O mesmo vale para um
 * `DB::table('users')`, para o psql e para qualquer cliente externo. O
 * único lugar de onde nada escapa é o próprio banco.
 *
 * Um scope global resolveria o update/delete em massa, mas ao preço de
 * ESCONDER as contas demo de toda leitura — inclusive do login, que é
 * justamente o que a demo precisa fazer. Trocar um buraco por outro.
 *
 * O gatilho recusa:
 *   - qualquer DELETE numa linha demo;
 *   - qualquer UPDATE que mexa nos campos sensíveis (DemoAccountGuard::
 *     SENSITIVE_ATTRIBUTES) de uma linha demo;
 *   - qualquer TRUNCATE da tabela `users` enquanto houver conta demo nela.
 * Trocar nome, foto, idioma ou tema continua passando.
 *
 * POR QUE O TRUNCATE PRECISA DE UM GATILHO PRÓPRIO: o gatilho de linha
 * (`FOR EACH ROW ... BEFORE UPDATE OR DELETE`) nunca é chamado por um
 * TRUNCATE — o TRUNCATE não apaga linha por linha, ele descarta o
 * armazenamento da tabela de uma vez, e o PostgreSQL só oferece gatilho de
 * TRUNCATE no nível da SENTENÇA. Sem este segundo gatilho, `TRUNCATE users`
 * (ou `User::truncate()`, ou o TRUNCATE ... CASCADE de outra tabela que
 * arraste `users` junto) apagava as contas demo sem esbarrar em nada. Ele
 * obedece à MESMA flag de sessão e, portanto, à MESMA porta
 * (DemoAccountGuard::withoutProtection()).
 *
 * Ele recusa só quando há conta demo na tabela: truncar uma tabela `users`
 * sem demo não tem o que proteger. E ele não interfere no fluxo de
 * desenvolvimento e de testes: `migrate:fresh`/`db:wipe` (e, portanto, o
 * RefreshDatabase) apagam a tabela com DROP, não com TRUNCATE, e o DROP leva
 * o gatilho junto.
 *
 * O QUE NENHUM GATILHO COBRE: quem é DONO da tabela pode desligar gatilho
 * (`ALTER TABLE ... DISABLE TRIGGER`) ou apagar a tabela inteira (DROP).
 * Isto aqui fecha o acidente e o atalho — o `update`/`delete`/`truncate`
 * distraído de código, tinker ou psql —, não um atacante com acesso de dono
 * ao banco. Contra esse, a defesa é a separação de papéis no PostgreSQL
 * (a aplicação conectando com uma role que não é dona do esquema), que é
 * decisão de infraestrutura.
 *
 * ESCAPE CONTROLADO: `SELECT set_config('tws.demo_guard','off',false)` na
 * sessão — é o que DemoAccountGuard::withoutProtection() faz para os
 * seeders, e o que DemoAccountSession faz em toda conexão da aplicação
 * quando o modo demo está DESLIGADO. Esse segundo uso é o que mantém o
 * gatilho alinhado com a flag: uma base que teve o modo demo ligado e passou
 * a rodar com ele desligado ainda tem o gatilho instalado (a migration que
 * o instalou já rodou), mas a aplicação deixa de esbarrar nele.
 *
 * SINCRONIA COM O CONFIG: os e-mails são gravados DENTRO da função no
 * momento da instalação. Por isso os seeders demo reinstalam o gatilho a
 * cada execução — mudou DEMO_USER_EMAIL no .env, o próximo `db:seed`
 * realinha o banco. Fora do PostgreSQL (SQLite dos testes) tudo aqui é
 * no-op e a proteção fica por conta da camada de model.
 */
final class DemoAccountTrigger
{
    public const FUNCTION = 'tws_protect_demo_users';

    public const TRIGGER = 'users_demo_account_guard';

    public const TRUNCATE_FUNCTION = 'tws_protect_demo_users_truncate';

    public const TRUNCATE_TRIGGER = 'users_demo_account_truncate_guard';

    public static function supported(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * (Re)instala função + gatilho com os e-mails demo vigentes. Com o modo
     * demo desligado, remove o gatilho: em produção não há o que proteger — e
     * um gatilho instalado lá tornaria as linhas demo indeletáveis justamente
     * onde elas precisam ser apagáveis.
     */
    public static function install(): void
    {
        if (! self::supported()) {
            return;
        }

        // Quem instala declara o estado da aplicação ao banco: a sessão
        // corrente passa a seguir o modo demo vigente (DemoAccountSession).
        DemoAccountGuard::syncDatabaseSession();

        if (! DemoSurface::loginEnabled()) {
            self::drop();

            return;
        }

        $emails = DemoAccountGuard::emails();

        if ($emails === []) {
            self::drop();

            return;
        }

        $lista = implode(', ', array_map(
            fn (string $email): string => "'".str_replace("'", "''", $email)."'",
            $emails,
        ));

        $sensiveis = implode("\n           OR ", array_map(
            fn (string $coluna): string => "NEW.{$coluna} IS DISTINCT FROM OLD.{$coluna}",
            DemoAccountGuard::SENSITIVE_ATTRIBUTES,
        ));

        $flag = DemoAccountGuard::DATABASE_FLAG;
        $funcao = self::FUNCTION;

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION {$funcao}() RETURNS trigger AS \$\$
        BEGIN
            IF coalesce(current_setting('{$flag}', true), 'on') = 'off' THEN
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END IF;

            IF OLD.email NOT IN ({$lista}) THEN
                IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                RETURN NEW;
            END IF;

            IF TG_OP = 'DELETE' THEN
                RAISE EXCEPTION 'TWS_DEMO_ACCOUNT_PROTECTED: conta demo % nao pode ser excluida', OLD.email
                    USING ERRCODE = 'raise_exception';
            END IF;

            IF {$sensiveis} THEN
                RAISE EXCEPTION 'TWS_DEMO_ACCOUNT_PROTECTED: conta demo % nao aceita alteracao de campo sensivel', OLD.email
                    USING ERRCODE = 'raise_exception';
            END IF;

            RETURN NEW;
        END;
        \$\$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER.' ON users');
        DB::unprepared(
            'CREATE TRIGGER '.self::TRIGGER.' BEFORE UPDATE OR DELETE ON users '
            .'FOR EACH ROW EXECUTE FUNCTION '.self::FUNCTION.'()',
        );

        self::installTruncateGuard($lista);
    }

    /**
     * Gatilho de SENTENÇA contra `TRUNCATE users` (ver o docblock da classe).
     *
     * A consulta usa o esquema e o nome da tabela do próprio gatilho
     * (TG_TABLE_SCHEMA/TG_TABLE_NAME) em vez de depender do search_path da
     * sessão que disparou o TRUNCATE.
     *
     * @param  string  $lista  E-mails demo já escapados como literais SQL.
     */
    private static function installTruncateGuard(string $lista): void
    {
        $flag = DemoAccountGuard::DATABASE_FLAG;
        $funcao = self::TRUNCATE_FUNCTION;

        DB::unprepared(<<<SQL
        CREATE OR REPLACE FUNCTION {$funcao}() RETURNS trigger AS \$\$
        DECLARE
            has_demo boolean;
        BEGIN
            IF coalesce(current_setting('{$flag}', true), 'on') = 'off' THEN
                RETURN NULL;
            END IF;

            EXECUTE format(
                'SELECT EXISTS (SELECT 1 FROM %I.%I WHERE email = ANY (\$1))',
                TG_TABLE_SCHEMA, TG_TABLE_NAME
            ) INTO has_demo USING ARRAY[{$lista}]::text[];

            IF has_demo THEN
                RAISE EXCEPTION 'TWS_DEMO_ACCOUNT_PROTECTED: TRUNCATE de % recusado: a tabela contem contas demo', TG_TABLE_NAME
                    USING ERRCODE = 'raise_exception';
            END IF;

            RETURN NULL;
        END;
        \$\$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRUNCATE_TRIGGER.' ON users');
        DB::unprepared(
            'CREATE TRIGGER '.self::TRUNCATE_TRIGGER.' BEFORE TRUNCATE ON users '
            .'FOR EACH STATEMENT EXECUTE FUNCTION '.self::TRUNCATE_FUNCTION.'()',
        );
    }

    public static function drop(): void
    {
        if (! self::supported()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER.' ON users');
        DB::unprepared('DROP FUNCTION IF EXISTS '.self::FUNCTION.'()');

        self::dropTruncateGuard();
    }

    /**
     * Remove só o gatilho de TRUNCATE (o `down` da migration que o
     * acrescentou a bancos já instalados).
     */
    public static function dropTruncateGuard(): void
    {
        if (! self::supported()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRUNCATE_TRIGGER.' ON users');
        DB::unprepared('DROP FUNCTION IF EXISTS '.self::TRUNCATE_FUNCTION.'()');
    }

    /**
     * O gatilho está instalado neste banco?
     */
    public static function installed(): bool
    {
        if (! self::supported()) {
            return false;
        }

        return DB::table('pg_trigger')->where('tgname', self::TRIGGER)->exists();
    }

    /**
     * O gatilho de TRUNCATE está instalado neste banco?
     */
    public static function truncateGuardInstalled(): bool
    {
        if (! self::supported()) {
            return false;
        }

        return DB::table('pg_trigger')->where('tgname', self::TRUNCATE_TRIGGER)->exists();
    }
}
