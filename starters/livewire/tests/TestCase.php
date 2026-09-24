<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: a suíte APAGA o banco (RefreshDatabase roda
     * `migrate:fresh`). Em SQLite ela usa memória; em qualquer outro banco,
     * só aceita um banco cujo nome termine em `_test` — nunca o banco de
     * desenvolvimento do .env, nem o de produção.
     *
     * Roda antes das traits (é nelas que o banco é recriado).
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Suíte recusada: o banco "%s" (%s) não é de teste. Use um banco cujo nome termine em _test (ver phpunit.pgsql.xml e docs/testes.md).',
                $database,
                $driver,
            ));
        }

        return parent::setUpTraits();
    }
}
