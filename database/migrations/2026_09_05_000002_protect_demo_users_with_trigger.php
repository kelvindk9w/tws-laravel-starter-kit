<?php

declare(strict_types=1);

use App\Core\Auth\Support\DemoAccountTrigger;
use Illuminate\Database\Migrations\Migration;

/**
 * Gatilho do PostgreSQL que blinda as CONTAS DEMO no próprio banco.
 *
 * Motivo (ver DemoAccountGuard): os eventos de model não veem
 * `User::where(...)->delete()` nem `->update([...])` — o Eloquent manda uma
 * única sentença. Só o banco fecha essa porta.
 *
 * A migration é um no-op fora do PostgreSQL (o SQLite dos testes fica com a
 * camada de model) e também quando o modo demo está desligado — em produção
 * não existe conta demo para proteger, e apagar `demo@…` de lá precisa
 * continuar possível.
 *
 * Os e-mails ficam gravados dentro da função no momento da instalação; os
 * seeders demo reinstalam o gatilho a cada `db:seed`, então trocar
 * DEMO_USER_EMAIL no .env realinha o banco sem migration nova.
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
