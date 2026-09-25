<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Twstec\Kit\Demo\Accounts\DemoAccountTrigger;

/**
 * Gatilho das contas demo reinstalado DEPOIS da coluna `two_factor_enabled_at`
 * (migration 2026_09_24_000002, do produto).
 *
 * A coluna está na lista de campos blindados das contas demo
 * (DemoAccountGuard::SENSITIVE_ATTRIBUTES): ligar o segundo fator numa conta
 * de senha pública mandaria o código para uma caixa que ninguém lê e trancaria
 * a demo para todo mundo. O gatilho é gerado a partir da lista, por isso é
 * reinstalado depois de a coluna existir.
 *
 * Até a separação da demo, esta reinstalação ficava dentro da própria
 * migration da coluna. Num banco que já rodou aquela versão, esta aqui só
 * repete a instalação (`install()` é idempotente). Fora do PostgreSQL, ou com
 * o modo demo desligado, `install()` não instala nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DemoAccountTrigger::install();
    }

    public function down(): void
    {
        // O gatilho referencia a coluna: sai antes dela (o down da 000002
        // remove a coluna em seguida). Quem volta o código para a versão
        // anterior reinstala o gatilho com o próximo `db:seed` das contas demo.
        DemoAccountTrigger::drop();
    }
};
