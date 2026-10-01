<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Twstec\Kit\Demo\Accounts\DemoAccountTrigger;

/**
 * Gatilho das contas demo reinstalado DEPOIS da coluna `admin_role`
 * (migration 2026_10_01_100000 do twstec/kit-admin, 2.0.0-beta.8).
 *
 * O papel no painel entrou na lista de campos blindados das contas demo
 * (DemoAccountGuard::SENSITIVE_ATTRIBUTES): rebaixar o admin demo, ou dar
 * papel ao cliente demo, mudaria o que o próximo visitante encontra. O
 * gatilho é gerado a partir da lista, por isso é reinstalado depois de a
 * coluna existir. Fora do PostgreSQL, ou com o modo demo desligado,
 * `install()` não instala nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DemoAccountTrigger::install();
    }

    public function down(): void
    {
        DemoAccountTrigger::drop();
    }
};
