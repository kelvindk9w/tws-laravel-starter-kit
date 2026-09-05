<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O formulário de contato REAL da landing passou a gravar em
 * form_submissions (origem `contact`), junto dos dois forms demo do /ui.
 *
 * Diferente dos demos (apelido anônimo), a mensagem de contato tem um
 * remetente de verdade: sem o e-mail dele a trilha do admin não serve para
 * responder nem para auditar. Nullable porque os forms demo não têm
 * remetente — a coluna só é preenchida pela origem `contact`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('sender_email', 255)->nullable()->after('nickname');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('sender_email');
        });
    }
};
