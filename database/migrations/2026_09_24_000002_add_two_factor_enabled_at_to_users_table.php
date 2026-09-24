<?php

declare(strict_types=1);

use App\Core\Auth\Support\DemoAccountTrigger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificação em duas etapas no login (opcional, por conta).
 *
 * `two_factor_enabled_at` guarda QUANDO a pessoa ligou o segundo fator (nulo =
 * desligado). É uma preferência só, lida pelo login do painel do cliente e
 * pelo login do /admin — os dois autenticam o MESMO guard de sessão, então
 * preferências separadas deixariam uma porta sem o segundo fator. Ver
 * App\Core\Auth\Services\TwoFactorLogin.
 *
 * A coluna entra na lista de campos blindados das contas demo
 * (DemoAccountGuard::SENSITIVE_ATTRIBUTES): ligar o segundo fator numa conta
 * de senha pública mandaria o código para uma caixa que ninguém lê e trancaria
 * a demo para todo mundo. Por isso o gatilho do PostgreSQL é reinstalado aqui,
 * depois de a coluna existir (o gatilho é gerado a partir da lista). Fora do
 * PostgreSQL, ou com o modo demo desligado, `install()` não faz nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('two_factor_enabled_at')->nullable()->after('transaction_password_set_at');
        });

        DemoAccountTrigger::install();
    }

    public function down(): void
    {
        // O gatilho referencia a coluna: sai antes dela. Quem volta o código
        // para a versão anterior reinstala o gatilho com o próximo `db:seed`
        // das contas demo (os seeders reinstalam a cada execução).
        DemoAccountTrigger::drop();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('two_factor_enabled_at');
        });
    }
};
