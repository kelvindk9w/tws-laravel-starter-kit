<?php

declare(strict_types=1);

namespace App\Core\Auth\Support;

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
 *     SENSITIVE_ATTRIBUTES) de uma linha demo.
 * Trocar nome, foto, idioma ou tema continua passando.
 *
 * ESCAPE CONTROLADO: `SELECT set_config('tws.demo_guard','off',false)` na
 * sessão — é o que DemoAccountGuard::withoutProtection() faz para os
 * seeders. Fora dos seeders, ninguém deveria precisar disso.
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

    public static function supported(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * (Re)instala função + gatilho com os e-mails demo vigentes. Com o modo
     * demo desligado, remove o gatilho: em produção não há o que proteger.
     */
    public static function install(): void
    {
        if (! self::supported()) {
            return;
        }

        if (! (bool) config('ui.demo_login.enabled')) {
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
    }

    public static function drop(): void
    {
        if (! self::supported()) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS '.self::TRIGGER.' ON users');
        DB::unprepared('DROP FUNCTION IF EXISTS '.self::FUNCTION.'()');
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
}
