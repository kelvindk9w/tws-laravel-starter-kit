<?php

declare(strict_types=1);

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
 * Esta migration já reinstalou aqui o gatilho das contas demo, que passa a
 * proteger a coluna nova. Isso agora é da demonstração: a migration
 * 2026_09_24_000003 (demo/database/migrations) faz o mesmo logo em seguida —
 * num banco que já rodou esta versão, ela só repete a instalação idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('two_factor_enabled_at')->nullable()->after('transaction_password_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('two_factor_enabled_at');
        });
    }
};
