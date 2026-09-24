<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contas que já existiam quando a verificação de e-mail passou a ser exigida
 * nascem confirmadas.
 *
 * Sem isto, o deploy da atualização trancaria todo mundo que se cadastrou
 * antes — gente que nunca recebeu e-mail de verificação — na tela de aviso (e
 * derrubaria as chaves de API dessas contas). A regra nova vale para quem se
 * cadastra DAQUI EM DIANTE.
 *
 * Numa instalação nova a tabela está vazia e nada acontece. O `down` não
 * desfaz: não há como distinguir, depois, quem foi marcado aqui de quem
 * confirmou pelo link.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Só a coluna `email_verified_at`, que a blindagem das contas demo
        // (gatilho do PostgreSQL) deixa passar.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Intencionalmente vazio — ver o docblock.
    }
};
