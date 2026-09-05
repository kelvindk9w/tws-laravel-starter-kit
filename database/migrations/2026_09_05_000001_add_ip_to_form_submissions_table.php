<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IP de origem da submissão.
 *
 * A tela de detalhe do super admin passou a ser a EVIDÊNCIA FORENSE de uma
 * tentativa de ataque (payload íntegro + metadados). Sem o IP, a evidência
 * responde "o quê" e não responde "de onde": não dá para correlacionar a
 * tentativa com os request_logs, nem para reconhecer a mesma origem
 * insistindo em vários formulários. O IP já era registrado no log
 * (FormSubmissionGuard) — só não sobrevivia junto do registro.
 *
 * Nullable: submissões anteriores a esta migration não têm o dado, e o
 * campo pode faltar em execuções fora de uma requisição HTTP (console).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('origin');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
};
