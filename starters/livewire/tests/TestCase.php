<?php

namespace Tests;

use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Grupo dos testes que exercitam a DEMONSTRAÇÃO do kit (twstec/kit-demo):
     * tudo em tests/Demo e os casos do produto marcados com `->group('demo')`.
     */
    public const DEMO_GROUP = 'demo';

    /**
     * A demonstração é um pacote de desenvolvimento (require-dev) que pode
     * ser removido (`composer remove --dev twstec/kit-demo`). Sem ela, os
     * testes do grupo `demo` PULAM, com o motivo — a suíte do produto passa
     * com o `pest` de sempre, sem filtro de grupo.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(self::DEMO_GROUP, $this->groups(), true) && ! self::demoInstalled()) {
            $this->markTestSkipped('Demonstração do kit (twstec/kit-demo) não instalada.');
        }
    }

    /**
     * O pacote da demonstração está instalado neste aplicativo?
     */
    public static function demoInstalled(): bool
    {
        return InstalledVersions::isInstalled('twstec/kit-demo');
    }

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
